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
use Modules\Visa\Models\VisaCountry;

/**
 * Dịch vụ làm visa đang hiển thị trên website (/lam-visa). Chỉ thông tin công
 * khai của trang visa — không có hồ sơ khách, không có tên đối tác xử lý.
 */
#[Name('visa_services')]
#[Title('Dịch vụ làm visa')]
#[Description('Tra dịch vụ làm visa của PSV Travel theo quốc gia: loại visa, phí dịch vụ (VND), thời gian xử lý, tỉ lệ đậu, giấy tờ cần chuẩn bị và link trang dịch vụ. Bỏ trống quốc gia để xem mọi nước. Look up PSV Travel visa services by country.')]
#[IsReadOnly]
#[IsIdempotent]
class DichVuVisa extends Tool
{
    private const LOAI = ['tourist' => 'Du lịch', 'business' => 'Công tác', 'study' => 'Du học'];

    public function schema(JsonSchema $schema): array
    {
        return [
            'quoc_gia' => $schema->string()->max(100)
                ->description('Tên quốc gia, VD "Hàn Quốc", "Nhật Bản", "Úc". Không dấu cũng được.'),
            'loai' => $schema->string()->enum(array_keys(self::LOAI))
                ->description('tourist = du lịch, business = công tác, study = du học'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $d = $request->validate([
            'quoc_gia' => ['nullable', 'string', 'max:100'],
            'loai' => ['nullable', 'in:'.implode(',', array_keys(self::LOAI))],
        ]);

        $ds = VisaCountry::query()->dangHienThi()
            ->when(isset($d['loai']), fn ($q) => $q->where('visa_type', $d['loai']))
            ->get()
            ->filter(fn (VisaCountry $v) => HoTro::khop($d['quoc_gia'] ?? null, $v->name, $v->slug))
            ->map(fn (VisaCountry $v) => [
                'quoc_gia' => $v->name,
                'loai_visa' => self::LOAI[$v->visa_type] ?? $v->visa_type,
                'phi_dich_vu_vnd' => $v->price,
                'thoi_gian_xu_ly' => $v->processing_time,
                'ti_le_dau_phan_tram' => $v->success_rate,
                'giay_to_can' => array_values(array_filter($v->required_documents ?? [], 'is_string')),
                'gioi_thieu' => HoTro::chu($v->description, 500),
                'url' => HoTro::web('lam-visa/'.$v->slug),
            ])
            ->values();

        return Response::structured([
            'so_dich_vu' => $ds->count(),
            'dich_vu' => $ds->all(),
            'ghi_chu' => 'Phí và giấy tờ có thể thay đổi theo từng hồ sơ; nhân viên visa PSV Travel tư vấn cụ thể khi nhận hồ sơ.',
            'xem_tat_ca' => HoTro::web('lam-visa'),
        ]);
    }
}
