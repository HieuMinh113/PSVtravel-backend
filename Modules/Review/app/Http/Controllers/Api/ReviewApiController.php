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

        if ($this->daDanhGia($userId, $tour->id)) {
            return response()->json([
                'data' => ['can_review' => false, 'reason' => 'Bạn đã đánh giá tour này rồi.'],
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

        // Chặn 2: mỗi tài khoản chỉ đánh giá 1 lần cho mỗi tour
        if ($this->daDanhGia($user->id, $data['tour_id'])) {
            return response()->json([
                'message' => 'Bạn đã đánh giá tour này rồi.',
            ], 409);
        }

        Review::create([
            'tour_id' => $data['tour_id'],
            'user_id' => $user->id,
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

    private function daDanhGia(int $userId, int $tourId): bool
    {
        return Review::query()
            ->where('user_id', $userId)
            ->where('tour_id', $tourId)
            ->exists();
    }
}
