<?php

namespace App\Mcp\Tools\NoiBo;

use App\Filament\Resources\MaldivesBookings\MaldivesBookingResource;
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
use Modules\Maldives\Models\MaldivesBooking;

#[Name('search_maldives_bookings')]
#[Title('Tìm đơn Maldives')]
#[Description('Tìm đơn combo Maldives trong admin theo mã đơn (MDV-…), tên khách hoặc số điện thoại; lọc theo trạng thái, ngày đi. Trả về combo, ngày đi, loại phòng, số khách, tiền USD/VND, đã thu, người phụ trách, ghi chú. Số điện thoại khách đã che bớt.')]
#[IsReadOnly]
#[IsIdempotent]
class TimDonMaldives extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'tu_khoa' => $schema->string()->max(100)->description('Mã đơn, tên khách hoặc số điện thoại'),
            'trang_thai' => $schema->string()->enum(array_keys(MaldivesBooking::TRANG_THAI))
                ->description(collect(MaldivesBooking::TRANG_THAI)->map(fn ($v, $k) => "$k = $v")->implode(', ')),
            'di_tu' => $schema->string()->description('Ngày đi từ (YYYY-MM-DD)'),
            'di_den' => $schema->string()->description('Ngày đi đến (YYYY-MM-DD)'),
            'so_ket_qua' => $schema->integer()->min(1)->max(50)->default(20),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($loi = QuanTri::thieuQuyen($request->user(), 'viewAny', 'xem đơn Maldives', MaldivesBooking::class)) {
            return Response::error($loi);
        }
        $d = $request->validate([
            'tu_khoa' => ['nullable', 'string', 'max:100'],
            'trang_thai' => ['nullable', 'in:'.implode(',', array_keys(MaldivesBooking::TRANG_THAI))],
            'di_tu' => ['nullable', 'date_format:Y-m-d'],
            'di_den' => ['nullable', 'date_format:Y-m-d'],
            'so_ket_qua' => ['nullable', 'integer', 'min:1', 'max:50'],
        ], QuanTri::THONG_BAO);

        $q = QuanTri::timTuKhoa(MaldivesBooking::query(), $d['tu_khoa'] ?? null, ['code', 'customer_name'], 'customer_phone')
            ->when(isset($d['trang_thai']), fn ($q) => $q->where('status', $d['trang_thai']))
            ->when(isset($d['di_tu']), fn ($q) => $q->whereDate('start_date', '>=', $d['di_tu']))
            ->when(isset($d['di_den']), fn ($q) => $q->whereDate('start_date', '<=', $d['di_den']));

        $tong = (clone $q)->count();
        $ds = $q->with('nguoiPhuTrach:id,name')->latest('id')->limit($d['so_ket_qua'] ?? 20)->get();

        return Response::structured([
            'so_don_tim_thay' => $tong,
            'don' => $ds->map(fn (MaldivesBooking $b) => [
                'ma_don' => $b->code,
                'khach' => $b->customer_name,
                'sdt' => QuanTri::sdt($b->customer_phone),
                'combo' => $b->combo_name,
                'ngay_di' => QuanTri::ngay($b->start_date),
                'noi_khoi_hanh' => $b->departure_city,
                'loai_phong' => $b->room_type_name,
                'so_phong' => $b->rooms,
                'nguoi_lon' => $b->adults,
                'tre_em' => $b->children,
                'em_be' => $b->infants,
                'tong_usd' => $b->total_usd,
                'tong_vnd' => $b->total_vnd,
                'da_thu_vnd' => (int) $b->paid_vnd,
                'trang_thai' => MaldivesBooking::TRANG_THAI[$b->status] ?? $b->status,
                'phu_trach' => $b->nguoiPhuTrach?->name,
                'ghi_chu_cua_khach' => $b->note,
                'ghi_chu_noi_bo' => $b->admin_note,
                'ngay_tao' => QuanTri::ngay($b->created_at),
                'link_admin' => MaldivesBookingResource::getUrl('view', ['record' => $b->id], panel: 'admin'),
            ])->all(),
        ]);
    }
}
