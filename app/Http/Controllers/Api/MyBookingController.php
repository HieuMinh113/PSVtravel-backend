<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Booking\Models\Booking;

class MyBookingController extends Controller
{
    // GET /api/v1/auth/bookings  (cần token) — lịch sử đặt tour của chính người đang đăng nhập
    public function index(Request $request): JsonResponse
    {
        $bookings = Booking::query()
            ->where('user_id', $request->user()->id) // chỉ đơn của chính mình
            ->with(['tour:id,slug,name,cover_image,type', 'departure:id,start_date'])
            ->latest('id')
            ->paginate(min((int) $request->query('per_page', 10), 30));

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
            ]),
            'meta' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'total' => $bookings->total(),
            ],
        ]);
    }
}
