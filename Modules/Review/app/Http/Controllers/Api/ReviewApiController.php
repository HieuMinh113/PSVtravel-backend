<?php

namespace Modules\Review\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Booking\Models\Booking;
use Modules\Review\Http\Requests\StoreReviewRequest;
use Modules\Review\Models\Review;
use Modules\Review\Transformers\ReviewResource;
use Modules\Tour\Models\Tour;

class ReviewApiController extends Controller
{
    // Chỉ đơn ở các trạng thái này mới được coi là "đã thực sự đi tour"
    private const TRANG_THAI_HOP_LE = ['confirmed', 'completed'];

    // GET /api/v1/reviews/featured — đánh giá tốt để hiển thị trang chủ
    public function featured(Request $request)
    {
        $reviews = Review::query()
            ->daDuyet()
            ->where('rating', '>=', 4)
            ->with('tour:id,name,cover_image')
            ->latest()
            ->take(min((int) $request->query('limit', 8), 20))
            ->get();

        return ReviewResource::collection($reviews);
    }

    // GET /api/v1/reviews/can-review/{slug}  (cần đăng nhập)
    // Frontend gọi để biết có nên hiện ô viết đánh giá hay không.
    public function canReview(Request $request, string $slug): JsonResponse
    {
        $tour = Tour::query()->where('slug', $slug)->firstOrFail();
        $userId = $request->user()->id;

        // Đơn đã đi xong mà CHƯA đánh giá — mỗi đơn được một bài.
        // Khách đi lại tour này ở đợt khác thì có đơn mới, viết được bài mới.
        $donChuaDanhGia = $this->donChuaDanhGia($userId, $tour->id);

        if ($donChuaDanhGia === null && $this->daDiTour($userId, $tour->id)) {
            return response()->json([
                'data' => ['can_review' => false, 'reason' => 'Bạn đã đánh giá tất cả chuyến đi của tour này rồi.'],
            ]);
        }

        if (! $this->daDiTour($userId, $tour->id)) {
            return response()->json([
                'data' => [
                    'can_review' => false,
                    'reason' => 'Chỉ khách đã đặt và hoàn tất tour này mới có thể đánh giá.',
                ],
            ]);
        }

        return response()->json([
            'data' => ['can_review' => true, 'tour_id' => $tour->id],
        ]);
    }

    // POST /api/v1/reviews  (cần đăng nhập)
    public function store(StoreReviewRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        // Chặn 1: phải từng đặt tour này và đơn đã được xác nhận/hoàn thành
        if (! $this->daDiTour($user->id, $data['tour_id'])) {
            return response()->json([
                'message' => 'Chỉ khách đã đặt và hoàn tất tour này mới có thể đánh giá.',
            ], 403);
        }

        // Chặn 2: mỗi ĐƠN chỉ được một đánh giá.
        // Đi lại cùng tour ở đợt khác là đơn khác, vẫn viết được bài mới.
        $don = $this->donChuaDanhGia($user->id, $data['tour_id']);

        if (! $don) {
            return response()->json([
                'message' => 'Bạn đã đánh giá tất cả chuyến đi của tour này rồi.',
            ], 409);
        }

        Review::create([
            'tour_id' => $data['tour_id'],
            'user_id' => $user->id,
            'booking_id' => $don->id,
            'customer_name' => $user->name,   // lấy từ tài khoản, khách không tự đặt tên khác
            'rating' => $data['rating'],
            'content' => $data['content'],
            'status' => 'pending',            // chờ nhân viên duyệt mới hiển thị
        ]);

        return response()->json([
            'message' => 'Cảm ơn bạn! Đánh giá đang chờ duyệt và sẽ hiển thị sau ít phút.',
        ], 201);
    }

    private function daDiTour(int $userId, int $tourId): bool
    {
        return Booking::query()
            ->where('user_id', $userId)
            ->where('tour_id', $tourId)
            ->whereIn('status', self::TRANG_THAI_HOP_LE)
            ->exists();
    }

    /**
     * Đơn đã đi xong của người này cho tour này mà chưa có đánh giá nào.
     * Trả về null nghĩa là mọi chuyến đi đều đã được đánh giá.
     */
    private function donChuaDanhGia(int $userId, int $tourId): ?Booking
    {
        return Booking::query()
            ->where('user_id', $userId)
            ->where('tour_id', $tourId)
            ->whereIn('status', self::TRANG_THAI_HOP_LE)
            ->whereNotExists(fn ($q) => $q
                ->selectRaw('1')
                ->from('reviews')
                ->whereColumn('reviews.booking_id', 'bookings.id'))
            ->oldest('id')
            ->first();
    }
}
