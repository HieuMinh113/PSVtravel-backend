<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventReviewController extends Controller
{
    // GET /api/v1/events/{slug}/reviews — các đánh giá đã duyệt của gói
    public function index(string $slug)
    {
        $event = Event::query()->dangHienThi()->where('slug', $slug)->firstOrFail();

        $reviews = EventReview::query()
            ->where('event_id', $event->id)
            ->daDuyet()
            ->latest()
            ->take(50)
            ->get()
            ->map(fn (EventReview $r) => [
                'id' => $r->id,
                'customer_name' => $r->customer_name,
                'rating' => $r->rating,
                'content' => $r->content,
                'admin_reply' => $r->admin_reply,
                'created_at' => $r->created_at?->format('Y-m-d'),
            ]);

        return response()->json([
            'data' => $reviews,
            'rating' => $event->rating ? (float) $event->rating : null,
            'review_count' => (int) $event->review_count,
        ]);
    }

    // POST /api/v1/events/{slug}/reviews — khách gửi đánh giá (chờ duyệt)
    public function store(Request $request, string $slug): JsonResponse
    {
        $event = Event::query()->dangHienThi()->where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'content' => ['required', 'string', 'min:10', 'max:2000'],
            // Ô bẫy chống bot — người thật không thấy nên luôn để trống
            'website' => ['nullable', 'size:0'],
        ], [
            'customer_name.required' => 'Vui lòng nhập tên của bạn.',
            'rating.required' => 'Vui lòng chọn số sao.',
            'rating.min' => 'Số sao phải từ 1 đến 5.',
            'rating.max' => 'Số sao phải từ 1 đến 5.',
            'content.required' => 'Vui lòng nhập nội dung đánh giá.',
            'content.min' => 'Nội dung đánh giá quá ngắn (tối thiểu 10 ký tự).',
            'website.size' => 'Yêu cầu không hợp lệ.',
        ]);

        // Cùng nội dung y hệt trong 5 phút coi là bấm nhầm hai lần — nhận im lặng
        $trung = EventReview::query()
            ->where('event_id', $event->id)
            ->where('content', $data['content'])
            ->where('created_at', '>=', now()->subMinutes(5))
            ->exists();

        if (! $trung) {
            EventReview::create([
                'event_id' => $event->id,
                'customer_name' => $data['customer_name'],
                'rating' => $data['rating'],
                'content' => $data['content'],
                'status' => 'pending',   // chờ nhân viên duyệt mới hiển thị
                'ip' => $request->ip(),
            ]);
        }

        return response()->json([
            'message' => 'Cảm ơn bạn! Đánh giá đang chờ duyệt và sẽ hiển thị sau khi được kiểm duyệt.',
        ], 201);
    }
}
