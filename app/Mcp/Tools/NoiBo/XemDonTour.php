<?php

namespace App\Mcp\Tools\NoiBo;

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
use Modules\Booking\Models\Payment;

#[Name('get_booking')]
#[Title('Xem chi tiết đơn đặt tour')]
#[Description('Chi tiết một đơn tour theo mã đơn: số khách, giá, tiền cọc, các khoản đã thu, hạn nhắc đóng tiền, hạn nộp visa, ghi chú, lý do huỷ, và mẫu tin nhắn Zalo nhắc khách đóng tiền. Số điện thoại / email khách đã che bớt.')]
#[IsReadOnly]
#[IsIdempotent]
class XemDonTour extends Tool
{
    private const HINH_THUC = ['cash' => 'Tiền mặt', 'bank_transfer' => 'Chuyển khoản', 'card' => 'Quẹt thẻ'];

    public function schema(JsonSchema $schema): array
    {
        return [
            'ma_don' => $schema->string()->max(40)->required()->description('Mã đơn, ví dụ PSV-20261105-A3F9'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $u = $request->user();
        $d = $request->validate(['ma_don' => ['required', 'string', 'max:40']]);

        $b = Booking::query()->where('booking_code', strtoupper(trim($d['ma_don'])))
            ->with(['tour', 'departure', 'nguoiPhuTrach:id,name', 'payments' => fn ($q) => $q->orderBy('paid_at')])
            ->first();
        if (! $b) {
            return Response::error('Không tìm thấy đơn '.$d['ma_don'].' — thử search_bookings.');
        }
        if ($loi = QuanTri::thieuQuyen($u, 'view', 'xem đơn đặt tour', $b)) {
            return Response::error($loi);
        }

        return Response::structured([
            ...TimDonTour::tomTat($b),
            'email' => QuanTri::email($b->customer_email),
            'nguoi_lon' => (int) $b->adults,
            'tre_em' => (int) $b->children,
            'gia_nguoi_lon_vnd' => $b->unit_price_adult,
            'gia_tre_em_vnd' => $b->unit_price_child,
            'cho_bao_gia_tre_em' => $b->choBaoGiaTreEm(),
            'coc' => $b->deposit_percent ? [
                'ty_le' => Booking::phanTram($b->deposit_percent),
                'so_tien_vnd' => $b->deposit_amount,
                'da_du_coc' => $b->duCoc(),
            ] : null,
            'tinh_trang_tien' => Booking::TIEN[$b->tinhTrangTien()],
            'khoan_thu' => $b->payments->map(fn (Payment $p) => [
                'ngay' => QuanTri::gio($p->paid_at),
                'so_tien_vnd' => (int) $p->amount,
                'hinh_thuc' => self::HINH_THUC[$p->method] ?? $p->method,
                'trang_thai' => Payment::TRANG_THAI[$p->status] ?? $p->status,
            ])->all(),
            'han_nhac_dong_tien' => QuanTri::ngay($b->remind_on),
            'da_nhac_luc' => QuanTri::gio($b->reminded_at),
            'han_nop_visa' => QuanTri::ngay($b->hanNopVisa()),
            'nguon' => Booking::NGUON[$b->source] ?? $b->source,
            'ghi_chu_cua_khach' => $b->note,
            'ghi_chu_noi_bo' => $b->admin_note,
            'ly_do_huy' => $b->status === 'cancelled' ? $b->cancel_reason : null,
            'tin_nhan_nhac_dong_tien' => in_array($b->status, ['pending', 'confirmed'], true) && $b->conLai() > 0
                ? $b->tinNhanNhacTien() : null,
        ]);
    }
}
