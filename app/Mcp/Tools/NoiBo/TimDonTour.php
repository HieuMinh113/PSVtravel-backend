<?php

namespace App\Mcp\Tools\NoiBo;

use App\Filament\Resources\Bookings\BookingResource;
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
use Modules\Booking\Models\Booking;

#[Name('search_bookings')]
#[Title('Tìm đơn đặt tour')]
#[Description('Tìm đơn đặt tour trong admin theo mã đơn, tên khách hoặc số điện thoại; lọc theo trạng thái, thanh toán, ngày khởi hành, đơn tới hạn nhắc đóng tiền. Số điện thoại khách đã che bớt; xem chi tiết bằng get_booking hoặc mở link_admin.')]
#[IsReadOnly]
#[IsIdempotent]
class TimDonTour extends Tool
{
    public const TRANG_THAI = ['pending' => 'Chờ xử lý', 'confirmed' => 'Đã xác nhận', 'completed' => 'Hoàn thành', 'cancelled' => 'Đã huỷ'];

    public const THANH_TOAN = ['unpaid' => 'Chưa trả', 'partial' => 'Trả một phần', 'paid' => 'Đã trả đủ'];

    public function schema(JsonSchema $schema): array
    {
        return [
            'tu_khoa' => $schema->string()->max(100)->description('Mã đơn (PSV-…), tên khách hoặc số điện thoại'),
            'trang_thai' => $schema->string()->enum(array_keys(self::TRANG_THAI))->description('pending = chờ xử lý, confirmed = đã xác nhận, completed = hoàn thành, cancelled = đã huỷ'),
            'thanh_toan' => $schema->string()->enum(array_keys(self::THANH_TOAN))->description('unpaid = chưa trả, partial = trả một phần, paid = đã trả đủ'),
            'khoi_hanh_tu' => $schema->string()->description('Ngày khởi hành từ (YYYY-MM-DD)'),
            'khoi_hanh_den' => $schema->string()->description('Ngày khởi hành đến (YYYY-MM-DD)'),
            'toi_han_nhac_tien' => $schema->boolean()->description('true = chỉ đơn đã tới hạn nhắn khách đóng tiền mà chưa nhắn'),
            'cua_toi' => $schema->boolean()->description('true = chỉ đơn tôi phụ trách'),
            'so_ket_qua' => $schema->integer()->min(1)->max(50)->default(20),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $u = $request->user();
        if ($loi = QuanTri::thieuQuyen($u, 'viewAny', 'xem đơn đặt tour', Booking::class)) {
            return Response::error($loi);
        }
        $d = $request->validate([
            'tu_khoa' => ['nullable', 'string', 'max:100'],
            'trang_thai' => ['nullable', 'in:'.implode(',', array_keys(self::TRANG_THAI))],
            'thanh_toan' => ['nullable', 'in:'.implode(',', array_keys(self::THANH_TOAN))],
            'khoi_hanh_tu' => ['nullable', 'date_format:Y-m-d'],
            'khoi_hanh_den' => ['nullable', 'date_format:Y-m-d'],
            'toi_han_nhac_tien' => ['nullable', 'boolean'],
            'cua_toi' => ['nullable', 'boolean'],
            'so_ket_qua' => ['nullable', 'integer', 'min:1', 'max:50'],
        ], QuanTri::THONG_BAO);

        $q = QuanTri::timTuKhoa(Booking::query(), $d['tu_khoa'] ?? null, ['booking_code', 'customer_name'], 'customer_phone')
            ->when(isset($d['trang_thai']), fn ($q) => $q->where('status', $d['trang_thai']))
            ->when(isset($d['thanh_toan']), fn ($q) => $q->where('payment_status', $d['thanh_toan']))
            ->when(! empty($d['toi_han_nhac_tien']), fn ($q) => $q->toiHanNhac())
            ->when(! empty($d['cua_toi']), fn ($q) => $q->where('assigned_to', $u->id))
            ->when(isset($d['khoi_hanh_tu']) || isset($d['khoi_hanh_den']), fn ($q) => $q->whereHas('departure', fn ($dq) => $dq
                ->when(isset($d['khoi_hanh_tu']), fn ($x) => $x->whereDate('start_date', '>=', $d['khoi_hanh_tu']))
                ->when(isset($d['khoi_hanh_den']), fn ($x) => $x->whereDate('start_date', '<=', $d['khoi_hanh_den']))));

        $tong = (clone $q)->count();
        $ds = $q->with(['tour:id,name', 'departure:id,start_date', 'nguoiPhuTrach:id,name'])
            ->latest('id')->limit($d['so_ket_qua'] ?? 20)->get();

        return Response::structured([
            'so_don_tim_thay' => $tong,
            'don' => $ds->map(fn (Booking $b) => self::tomTat($b))->all(),
        ]);
    }

    /** Thông tin gọn của một đơn (không lộ số điện thoại / email đầy đủ) */
    public static function tomTat(Booking $b): array
    {
        return [
            'ma_don' => $b->booking_code,
            'khach' => $b->customer_name,
            'sdt' => QuanTri::sdt($b->customer_phone),
            'tour' => $b->tour?->name,
            'ngay_di' => QuanTri::ngay($b->departure?->start_date),
            'so_khach' => (int) $b->adults + (int) $b->children,
            'tong_tien_vnd' => (int) $b->total_price,
            'da_thu_vnd' => (int) $b->paid_total,
            'con_lai_vnd' => max(0, (int) $b->total_price - (int) $b->paid_total),
            'trang_thai' => self::TRANG_THAI[$b->status] ?? $b->status,
            'thanh_toan' => self::THANH_TOAN[$b->payment_status] ?? $b->payment_status,
            'phu_trach' => $b->nguoiPhuTrach?->name,
            'ngay_tao' => QuanTri::ngay($b->created_at),
            'link_admin' => BookingResource::getUrl('view', ['record' => $b->id], panel: 'admin'),
        ];
    }
}
