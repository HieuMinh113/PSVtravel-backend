<?php

namespace App\Mcp\Tools\NoiBo;

use App\Filament\Resources\VisaCases\VisaCaseResource;
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
use Modules\Visa\Models\VisaCase;

#[Name('visa_cases')]
#[Title('Hồ sơ visa đang làm')]
#[Description('Danh sách hồ sơ visa (mặc định các hồ sơ đang xử lý): quốc gia, ngày đi, trạng thái, lịch hẹn nộp, tiến độ giấy tờ và tên giấy còn thiếu, phí / đã thu, người phụ trách. KHÔNG trả số hộ chiếu, ngày sinh hay tệp giấy tờ của khách; số điện thoại đã che bớt. Nhân viên chỉ thấy hồ sơ mình phụ trách và hồ sơ chưa ai nhận, như trong admin.')]
#[IsReadOnly]
#[IsIdempotent]
class HoSoVisaMcp extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'trang_thai' => $schema->string()->enum([...array_keys(VisaCase::TRANG_THAI), 'tat_ca'])
                ->description(collect(VisaCase::TRANG_THAI)->map(fn ($v, $k) => "$k = $v")->implode(', ').', tat_ca = mọi hồ sơ. Bỏ trống = đang xử lý'),
            'quoc_gia' => $schema->string()->max(100)->description('Lọc theo nước xin visa (không dấu cũng được)'),
            'tu_khoa' => $schema->string()->max(100)->description('Mã hồ sơ, tên khách hoặc tên đoàn'),
            'hen_trong_so_ngay' => $schema->integer()->min(0)->max(60)->description('Chỉ hồ sơ có lịch hẹn nộp từ hôm nay tới N ngày nữa'),
            'so_ket_qua' => $schema->integer()->min(1)->max(50)->default(20),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $u = $request->user();
        if ($loi = QuanTri::thieuQuyen($u, 'viewAny', 'xem hồ sơ visa', VisaCase::class)) {
            return Response::error($loi);
        }
        $d = $request->validate([
            'trang_thai' => ['nullable', 'in:'.implode(',', [...array_keys(VisaCase::TRANG_THAI), 'tat_ca'])],
            'quoc_gia' => ['nullable', 'string', 'max:100'],
            'tu_khoa' => ['nullable', 'string', 'max:100'],
            'hen_trong_so_ngay' => ['nullable', 'integer', 'min:0', 'max:60'],
            'so_ket_qua' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $trangThai = $d['trang_thai'] ?? null;

        $ds = QuanTri::timTuKhoa(VisaCase::query()->nhinThayBoi($u), $d['tu_khoa'] ?? null, ['code', 'full_name', 'group_name'])
            ->when($trangThai === null, fn ($q) => $q->whereIn('status', VisaCase::DANG_XU_LY))
            ->when($trangThai && $trangThai !== 'tat_ca', fn ($q) => $q->where('status', $trangThai))
            ->when(isset($d['hen_trong_so_ngay']), fn ($q) => $q->whereBetween('appointment_at', [now()->startOfDay(), now()->addDays($d['hen_trong_so_ngay'])->endOfDay()]))
            ->with('nguoiPhuTrach:id,name')
            ->orderByRaw('appointment_at is null')->orderBy('appointment_at')->orderBy('travel_date')
            ->limit(1000)->get()
            ->filter(fn (VisaCase $h) => HoTro::khop($d['quoc_gia'] ?? null, (string) $h->country))
            ->values();

        return Response::structured([
            'so_ho_so' => $ds->count(),
            'ho_so' => $ds->take($d['so_ket_qua'] ?? 20)->map(function (VisaCase $h) {
                [$da, $can] = $h->tienDoGiayTo();

                return [
                    'ma' => $h->code,
                    'khach' => $h->full_name,
                    'doan' => $h->group_name,
                    'sdt' => QuanTri::sdt($h->phone),
                    'quoc_gia' => $h->country,
                    'muc_dich' => VisaCase::MUC_DICH[$h->purpose] ?? $h->purpose,
                    'ngay_di' => QuanTri::ngay($h->travel_date),
                    'trang_thai' => VisaCase::TRANG_THAI[$h->status] ?? $h->status,
                    'lich_hen_nop' => QuanTri::gio($h->appointment_at),
                    'ngay_nop' => QuanTri::ngay($h->submitted_on),
                    'du_kien_co_ket_qua' => QuanTri::ngay($h->result_expected_on),
                    'giay_to' => "{$da}/{$can}",
                    'giay_con_thieu' => $h->giayConThieu(),
                    'ho_chieu_con_han_duoi_6_thang' => $h->hoChieuSapHetHan(),
                    'phi_vnd' => (int) $h->fee,
                    'da_thu_vnd' => (int) $h->paid,
                    'con_phai_thu_vnd' => $h->conPhaiThu(),
                    'phu_trach' => $h->nguoiPhuTrach?->name,
                    'link_admin' => VisaCaseResource::getUrl('edit', ['record' => $h->id], panel: 'admin'),
                ];
            })->all(),
        ]);
    }
}
