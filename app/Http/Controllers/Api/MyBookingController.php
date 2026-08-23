<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Booking\Models\Booking;
use Modules\Review\Models\Review;

class MyBookingController extends Controller
{
    // GET /api/v1/auth/bookings  (cần token) — lịch sử đặt tour của chính người đang đăng nhập
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $bookings = Booking::query()
            ->where('user_id', $userId) // chỉ đơn của chính mình
            ->with(['tour:id,slug,name,cover_image,type', 'departure:id,start_date'])
            ->latest('id')
            ->paginate(min((int) $request->query('per_page', 10), 30));

        // Tour nào tài khoản này đã đánh giá rồi — lấy một lượt cho cả trang,
        // không hỏi lại theo từng đơn.
        $tourDaDanhGia = Review::query()
            ->where('user_id', $userId)
            ->whereIn('tour_id', $bookings->getCollection()->pluck('tour_id')->filter())
            ->pluck('tour_id')
            ->all();

        return response()->json([
            'data' => $bookings->getCollection()->map(fn (Booking $b) => [
                'booking_code' => $b->booking_code,
                'tour_name' => $b->tour?->name,
                'tour_slug' => $b->tour?->slug,
                'tour_type' => $b->tour?->type,
                'tour_image' => $b->tour?->cover_image
                    ? (str_starts_with($b->tour->cover_image, 'http')
                        ? $b->tour->cover_image
                        : asset('storage/'.$b->tour->cover_image))
                    : null,
                'start_date' => $b->departure?->start_date?->format('d/m/Y'),
                'adults' => $b->adults,
                'children' => $b->children,
                'total_price' => $b->total_price,
                'status' => $b->status,
                'status_label' => match ($b->status) {
                    'confirmed' => 'Đã xác nhận',
                    'completed' => 'Hoàn thành',
                    'cancelled' => 'Đã huỷ',
                    default => 'Chờ xử lý',
                },
                'payment_status' => $b->payment_status,
                'payment_label' => match ($b->payment_status) {
                    'paid' => 'Đã thanh toán',
                    'partial' => 'Trả một phần',
                    default => 'Chưa thanh toán',
                },
                'created_at' => $b->created_at?->format('d/m/Y H:i'),
                // Đơn đã hoàn thành thì khách được mời đánh giá; đã đánh giá rồi
                // thì nút đổi thành trạng thái, không cho gửi trùng.
                'co_the_danh_gia' => $b->status === 'completed'
                    && $b->tour_id
                    && ! in_array($b->tour_id, $tourDaDanhGia, true),
                'da_danh_gia' => $b->tour_id && in_array($b->tour_id, $tourDaDanhGia, true),
            ]),
            'meta' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'total' => $bookings->total(),
            ],
        ]);
    }
}
