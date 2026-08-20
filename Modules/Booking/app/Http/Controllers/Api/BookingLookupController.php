<?php

namespace Modules\Booking\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Booking\Models\Booking;

/**
 * Tra cứu đơn đặt tour cho khách KHÔNG đăng nhập.
 *
 * Đây là tính năng tạo niềm tin: khách đặt xong vẫn xem lại được đơn của mình
 * mà không cần tài khoản — giống travel.com.vn, dulichviet.com.vn.
 *
 * Bảo mật: bắt buộc đúng CẢ mã đơn LẪN số điện thoại đã đặt.
 * Chỉ có mã đơn thì không tra được, nên người khác nhặt được mã cũng vô ích.
 */
class BookingLookupController extends Controller
{
    // POST /api/v1/bookings/lookup
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'booking_code' => ['required', 'string', 'max:40'],
            'phone' => ['required', 'string', 'max:20'],
        ], [
            'booking_code.required' => 'Vui lòng nhập mã đơn.',
            'phone.required' => 'Vui lòng nhập số điện thoại đã dùng khi đặt.',
        ]);

        // Chuẩn hoá: mã đơn luôn viết hoa, số điện thoại bỏ khoảng trắng/dấu chấm
        $ma = strtoupper(trim($data['booking_code']));
        $sdt = preg_replace('/[^0-9+]/', '', $data['phone']);

        $booking = Booking::query()
            ->where('booking_code', $ma)
            ->with(['tour:id,slug,name,cover_image,type,duration_days', 'departure:id,start_date'])
            ->first();

        // Trả về CÙNG một thông báo cho mọi trường hợp sai — không hé lộ
        // rằng mã đơn có tồn tại hay không (tránh bị dò mã)
        if (! $booking || ! $this->trungSoDienThoai($booking->customer_phone, $sdt)) {
            return response()->json([
                'message' => 'Không tìm thấy đơn nào khớp với mã đơn và số điện thoại bạn nhập.',
            ], 404);
        }

        return response()->json(['data' => [
            'booking_code' => $booking->booking_code,
            'customer_name' => $booking->customer_name,
            'customer_phone' => $this->che($booking->customer_phone),
            'tour_name' => $booking->tour?->name,
            'tour_slug' => $booking->tour?->slug,
            'tour_type' => $booking->tour?->type,
            'tour_image' => $booking->tour?->cover_image
                ? (str_starts_with($booking->tour->cover_image, 'http')
                    ? $booking->tour->cover_image
                    : asset('storage/'.$booking->tour->cover_image))
                : null,
            'start_date' => $booking->departure?->start_date?->format('d/m/Y'),
            // Đợt khởi hành chỉ lưu ngày đi; ngày về suy ra từ số ngày của tour
            // (tour 3 ngày khởi hành 01/09 thì về ngày 03/09).
            'end_date' => $this->ngayVe($booking),
            'adults' => $booking->adults,
            'children' => $booking->children,
            'total_price' => $booking->total_price,
            'status' => $booking->status,
            'status_label' => match ($booking->status) {
                'confirmed' => 'Đã xác nhận',
                'completed' => 'Hoàn thành',
                'cancelled' => 'Đã huỷ',
                default => 'Chờ xử lý',
            },
            'payment_status' => $booking->payment_status,
            'payment_label' => match ($booking->payment_status) {
                'paid' => 'Đã thanh toán',
                'partial' => 'Trả một phần',
                default => 'Chưa thanh toán',
            },
            'created_at' => $booking->created_at?->format('d/m/Y H:i'),
        ]]);
    }

    private function ngayVe(Booking $booking): ?string
    {
        $ngayDi = $booking->departure?->start_date;
        $soNgay = (int) ($booking->tour?->duration_days ?? 0);

        if (! $ngayDi || $soNgay < 1) {
            return null;
        }

        return $ngayDi->copy()->addDays($soNgay - 1)->format('d/m/Y');
    }

    // So sánh số điện thoại bỏ qua định dạng: 0912..., +84912..., 84912... coi là một
    private function trungSoDienThoai(?string $trongDb, string $nhapVao): bool
    {
        if (! $trongDb) {
            return false;
        }

        return $this->chuanHoa($trongDb) === $this->chuanHoa($nhapVao);
    }

    private function chuanHoa(string $sdt): string
    {
        $so = preg_replace('/[^0-9]/', '', $sdt);

        // Đưa hết về dạng bắt đầu bằng 0
        if (str_starts_with($so, '84')) {
            $so = '0'.substr($so, 2);
        }

        return $so;
    }

    // Che bớt số điện thoại khi trả về: 0912345678 -> 0912***678
    private function che(?string $sdt): ?string
    {
        if (! $sdt || strlen($sdt) < 7) {
            return $sdt;
        }

        return substr($sdt, 0, 4).'***'.substr($sdt, -3);
    }
}
