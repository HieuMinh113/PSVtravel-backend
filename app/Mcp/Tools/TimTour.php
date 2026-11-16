<?php

namespace App\Mcp\Tools;

use App\Mcp\HoTro;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Modules\Tour\Models\Tour;
use Modules\Tour\Models\TourDeparture;
use Modules\Tour\Transformers\TourDetailResource;

/**
 * Tìm tour đang bán — cùng điều kiện với danh sách tour trên website: tour đã
 * đăng, chỉ kể các đợt còn nhận khách, chưa đi, chưa quá hạn nộp visa.
 */
#[Name('search_tours')]
#[Title('Tìm tour du lịch')]
#[Description('Tìm tour du lịch trọn gói PSV Travel đang mở bán theo điểm đến, loại tour (trong nước / nước ngoài), tháng khởi hành, giá tối đa và số ngày. Trả về giá từ (VND, người lớn), các đợt khởi hành gần nhất kèm số chỗ còn và link trang tour. Dùng get_tour để xem lịch trình chi tiết. Search PSV Travel package tours on sale (Vietnam domestic and international).')]
#[IsReadOnly]
#[IsIdempotent]
class TimTour extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'tu_khoa' => $schema->string()->max(100)
                ->description('Điểm đến, quốc gia hoặc tên tour, VD "Đà Nẵng", "Hàn Quốc", "Phú Quốc". Gõ không dấu cũng được. Bỏ trống để xem mọi tour.'),
            'loai' => $schema->string()->enum(['trong_nuoc', 'nuoc_ngoai'])
                ->description('trong_nuoc = tour trong nước, nuoc_ngoai = tour nước ngoài'),
            'thang_khoi_hanh' => $schema->string()->pattern('^\d{4}-\d{2}$')
                ->description('Chỉ lấy tour có đợt khởi hành trong tháng này, dạng YYYY-MM, VD "2026-12"'),
            'gia_toi_da' => $schema->integer()->min(0)
                ->description('Giá người lớn tối đa, đơn vị VND, VD 15000000'),
            'so_ngay_toi_da' => $schema->integer()->min(1)->max(60)
                ->description('Số ngày tối đa của tour'),
            'so_ket_qua' => $schema->integer()->min(1)->max(20)->default(10)
                ->description('Số tour trả về tối đa (mặc định 10)'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $d = $request->validate([
            'tu_khoa' => ['nullable', 'string', 'max:100'],
            'loai' => ['nullable', 'in:trong_nuoc,nuoc_ngoai'],
            'thang_khoi_hanh' => ['nullable', 'date_format:Y-m'],
            'gia_toi_da' => ['nullable', 'integer', 'min:0'],
            'so_ngay_toi_da' => ['nullable', 'integer', 'min:1', 'max:60'],
            'so_ket_qua' => ['nullable', 'integer', 'min:1', 'max:20'],
        ], [
            'thang_khoi_hanh.date_format' => 'thang_khoi_hanh phải có dạng YYYY-MM, VD "2026-12".',
            'loai.in' => 'loai chỉ nhận "trong_nuoc" hoặc "nuoc_ngoai".',
        ]);
        $thang = $d['thang_khoi_hanh'] ?? null;

        // Số tour đang bán chỉ vài trăm: lấy hết rồi lọc từ khoá bằng PHP để so
        // khớp không dấu (LIKE của CSDL phân biệt dấu, "da nang" không ra "Đà Nẵng")
        $tours = Tour::query()->hienChoKhach()
            ->with(['departures' => fn ($q) => $q->hienTrenWeb()->orderBy('start_date')])
            ->when(isset($d['loai']), fn ($q) => $q->where('type', $d['loai'] === 'nuoc_ngoai' ? 'abroad' : 'domestic'))
            ->when(isset($d['so_ngay_toi_da']), fn ($q) => $q->where('duration_days', '<=', $d['so_ngay_toi_da']))
            ->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('id')
            ->get()
            ->filter(fn (Tour $t) => HoTro::khop($d['tu_khoa'] ?? null, $t->name, (string) $t->region, (string) $t->country))
            ->map(function (Tour $t) use ($thang) {
                $dot = $thang
                    ? $t->departures->filter(fn (TourDeparture $x) => $x->start_date->format('Y-m') === $thang)->values()
                    : $t->departures;

                return [$t, $dot];
            })
            ->filter(fn ($cap) => ! $thang || $cap[1]->isNotEmpty())
            ->map(fn ($cap) => $this->the($cap[0], $cap[1]))
            ->filter(fn ($the) => ! isset($d['gia_toi_da']) || ($the['gia_tu_vnd'] !== null && $the['gia_tu_vnd'] <= $d['gia_toi_da']))
            ->values();

        return Response::structured([
            'so_tour_tim_thay' => $tours->count(),
            'tours' => $tours->take($d['so_ket_qua'] ?? 10)->all(),
            'ghi_chu' => 'Giá theo người lớn, đơn vị VND. Số chỗ thay đổi liên tục; nhân viên PSV Travel xác nhận khi khách đặt. Tour chưa có đợt khởi hành là tour tổ chức theo yêu cầu.',
            'xem_tat_ca' => HoTro::web(match ($d['loai'] ?? null) {
                'nuoc_ngoai' => 'tour-nuoc-ngoai',
                'trong_nuoc' => 'tour-trong-nuoc',
                default => '',
            }),
        ]);
    }

    /** Một dòng kết quả: thông tin đủ để AI so sánh và trả lời khách */
    private function the(Tour $t, $dot): array
    {
        $giaDot = $dot->map(fn (TourDeparture $x) => (int) ($x->price_override ?? $t->adult_price))->filter();
        $giaTu = $giaDot->isNotEmpty() ? $giaDot->min() : ($t->adult_price ? (int) $t->adult_price : null);

        return [
            'slug' => $t->slug,
            'ten' => $t->name,
            'loai' => $t->type === 'abroad' ? 'Nước ngoài' : 'Trong nước',
            'diem_den' => implode(' · ', array_filter([$t->region, $t->country])) ?: null,
            'thoi_gian' => $t->duration_days ? trim("{$t->duration_days} ngày ".($t->duration_nights ? "{$t->duration_nights} đêm" : '')) : null,
            'khoi_hanh_tu' => $t->departure_from,
            'gia_tu_vnd' => $giaTu,
            'gia_cu_vnd' => $t->old_price && $giaTu && $t->old_price > $giaTu ? (int) $t->old_price : null,
            'visa' => TourDetailResource::dangVisa($t)['visa_kind_label'],
            'so_dot_khoi_hanh' => $dot->count(),
            'dot_gan_nhat' => $dot->take(5)->map(fn (TourDeparture $x) => [
                'ngay' => $x->start_date->format('Y-m-d'),
                'gia_vnd' => (int) ($x->price_override ?? $t->adult_price),
                'con_cho' => $x->seats_left,
            ])->values()->all(),
            'url' => HoTro::urlTour($t->slug, $t->type),
        ];
    }
}
