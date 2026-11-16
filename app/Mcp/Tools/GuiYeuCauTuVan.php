<?php

namespace App\Mcp\Tools;

use App\Mcp\HoTro;
use App\Models\ContactMessage;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Modules\Maldives\Models\MaldivesCombo;
use Modules\Page\Models\Setting;
use Modules\Tour\Models\Tour;

/**
 * Trợ lý AI gửi yêu cầu tư vấn thay khách → vào hộp "Liên hệ" trong admin, nguồn
 * "Trợ lý AI (MCP)". Chỉ là yêu cầu gọi lại: không giữ chỗ, không tạo đơn, không
 * thu tiền — nhân viên gọi khách xác nhận như với form liên hệ trên website.
 *
 * Chống spam: cùng số điện thoại tối đa 3 yêu cầu / ngày; mỗi địa chỉ IP 20 / giờ
 * (yêu cầu từ ChatGPT, Claude… đi qua máy chủ chung của họ nên không đặt quá thấp).
 */
#[Name('submit_inquiry')]
#[Title('Gửi yêu cầu tư vấn cho PSV Travel')]
#[Description('Gửi yêu cầu tư vấn / đặt tour của khách tới nhân viên PSV Travel để họ gọi lại. CHỈ gọi khi khách đã tự cung cấp họ tên, số điện thoại và đồng ý rõ ràng cho PSV Travel liên hệ (khach_dong_y = true). Không giữ chỗ, không tạo đơn, không thu tiền — nhân viên sẽ gọi xác nhận còn chỗ và giá. Send a callback request to PSV Travel staff, only with the customer\'s explicit consent.')]
#[IsDestructive(false)]
#[IsIdempotent(false)]
class GuiYeuCauTuVan extends Tool
{
    public const NGUON = 'tro_ly_ai';

    public function schema(JsonSchema $schema): array
    {
        return [
            'ho_ten' => $schema->string()->max(100)->required()->description('Họ tên khách'),
            'so_dien_thoai' => $schema->string()->max(20)->required()->description('Số điện thoại khách, VD "0901234567"'),
            'email' => $schema->string()->max(255)->description('Email khách (không bắt buộc)'),
            'noi_dung' => $schema->string()->max(2000)->required()
                ->description('Khách cần tư vấn gì: điểm đến, ngày đi mong muốn, ngân sách, yêu cầu riêng… (ít nhất 10 ký tự)'),
            'tour_slug' => $schema->string()->max(200)
                ->description('Slug tour (từ search_tours) hoặc combo Maldives (từ maldives_combos) khách quan tâm, nếu có'),
            'so_nguoi' => $schema->string()->max(100)->description('Số người đi, VD "2 người lớn, 1 trẻ em 6 tuổi"'),
            'thoi_gian_du_kien' => $schema->string()->max(100)->description('Thời gian dự kiến đi, VD "cuối tháng 12/2026"'),
            'khach_dong_y' => $schema->boolean()->required()
                ->description('true khi khách đã đồng ý rõ ràng cho PSV Travel lưu thông tin và gọi lại tư vấn'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $d = $request->validate([
            'ho_ten' => ['required', 'string', 'max:100'],
            'so_dien_thoai' => ['required', 'string', 'regex:/^[0-9\s+.-]{8,20}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'noi_dung' => ['required', 'string', 'min:10', 'max:2000'],
            'tour_slug' => ['nullable', 'string', 'max:200'],
            'so_nguoi' => ['nullable', 'string', 'max:100'],
            'thoi_gian_du_kien' => ['nullable', 'string', 'max:100'],
            'khach_dong_y' => ['accepted'],
        ], [
            'khach_dong_y.accepted' => 'Cần khách đồng ý cho PSV Travel lưu thông tin và gọi lại (khach_dong_y = true) trước khi gửi.',
            'so_dien_thoai.regex' => 'Số điện thoại không hợp lệ.',
            'noi_dung.min' => 'Nội dung quá ngắn: ghi rõ khách cần tư vấn gì (điểm đến, ngày đi, số người…).',
        ]);

        $hotline = Setting::lay('hotline') ?: '0907 870 707';
        $soDt = preg_replace('/\D+/', '', $d['so_dien_thoai']);
        $khoaSo = 'mcp-yeu-cau:sdt:'.$soDt;
        $khoaIp = 'mcp-yeu-cau:ip:'.request()->ip();
        if (RateLimiter::tooManyAttempts($khoaSo, 3) || RateLimiter::tooManyAttempts($khoaIp, 20)) {
            return Response::error("Số điện thoại / kết nối này đã gửi nhiều yêu cầu. Nhân viên sẽ liên hệ theo yêu cầu đã gửi; cần gấp vui lòng gọi hotline {$hotline}.");
        }

        [$tenTour, $urlTour] = $this->tourQuanTam($d['tour_slug'] ?? null);
        $noiDung = trim(implode("\n", array_filter([
            $d['noi_dung'],
            $tenTour ? "Tour quan tâm: {$tenTour}" : null,
            ! empty($d['so_nguoi']) ? "Số người: {$d['so_nguoi']}" : null,
            ! empty($d['thoi_gian_du_kien']) ? "Thời gian dự kiến: {$d['thoi_gian_du_kien']}" : null,
        ])));

        // Trợ lý AI gọi lại y hệt trong 5 phút (thử lại khi mạng chập chờn) → không tạo trùng
        $trung = ContactMessage::query()
            ->where('phone', $d['so_dien_thoai'])
            ->where('message', $noiDung)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->exists();

        if (! $trung) {
            ContactMessage::create([
                'name' => $d['ho_ten'],
                'phone' => $d['so_dien_thoai'],
                'email' => $d['email'] ?? null,
                'subject' => 'Trợ lý AI: '.($tenTour ? "tư vấn {$tenTour}" : 'yêu cầu tư vấn'),
                'source' => self::NGUON,
                'message' => $noiDung,
                'meta' => array_filter([
                    'tour' => $tenTour,
                    'tour_url' => $urlTour,
                    'group_size' => $d['so_nguoi'] ?? null,
                    'event_date' => $d['thoi_gian_du_kien'] ?? null,
                ]) ?: null,
                'ip' => request()->ip(),
                'kenh' => 'tro_ly_ai_mcp',
            ]);
            RateLimiter::hit($khoaSo, 86400);
            RateLimiter::hit($khoaIp, 3600);
        }

        return Response::structured([
            'da_gui' => true,
            'loi_nhan' => 'Đã gửi yêu cầu tới PSV Travel. Nhân viên sẽ gọi lại cho khách trong giờ làm việc ('
                .(Setting::lay('working_hours') ?: 'Thứ 2 – Chủ nhật, 8:00 – 17:00')
                .") để tư vấn, xác nhận còn chỗ và báo giá. Cần gấp: hotline {$hotline} (24/7).",
        ]);
    }

    /** Tên + link tour / combo khách quan tâm (slug sai thì bỏ qua, không báo lỗi) */
    private function tourQuanTam(?string $slug): array
    {
        if (! $slug) {
            return [null, null];
        }
        if ($t = Tour::query()->published()->where('slug', $slug)->first(['slug', 'name', 'type'])) {
            return [$t->name, HoTro::urlTour($t->slug, $t->type)];
        }
        if ($c = MaldivesCombo::query()->dangBan()->where('slug', $slug)->first(['maldives_combos.slug', 'maldives_combos.name'])) {
            return [$c->name, HoTro::web('maldives/'.$c->slug)];
        }

        return [null, null];
    }
}
