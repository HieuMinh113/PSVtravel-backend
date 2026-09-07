<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    // POST /api/v1/contact — form liên hệ ngoài website
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^[0-9\s+.-]{8,20}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
            // Ô bẫy: người thật không thấy ô này nên luôn để trống.
            // Bot điền hết mọi ô sẽ tự lộ ra ở đây.
            'website' => ['nullable', 'size:0'],
        ], [
            'name.required' => 'Vui lòng nhập họ tên.',
            'phone.required' => 'Vui lòng nhập số điện thoại.',
            'phone.regex' => 'Số điện thoại không hợp lệ.',
            'message.required' => 'Vui lòng nhập nội dung cần tư vấn.',
            'message.min' => 'Nội dung quá ngắn, vui lòng mô tả rõ hơn.',
            'website.size' => 'Yêu cầu không hợp lệ.',
        ]);

        // Cùng một số điện thoại gửi lại nội dung y hệt trong 5 phút thì coi là
        // bấm nhầm hai lần — nhận im lặng, không tạo thêm bản ghi rác.
        $trung = ContactMessage::query()
            ->where('phone', $data['phone'])
            ->where('message', $data['message'])
            ->where('created_at', '>=', now()->subMinutes(5))
            ->exists();

        if (! $trung) {
            ContactMessage::create([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'subject' => $data['subject'] ?? null,
                'message' => $data['message'],
                'ip' => $request->ip(),
            ]);
        }

        return response()->json([
            'message' => 'Đã nhận thông tin. Chúng tôi sẽ liên hệ lại với bạn trong thời gian sớm nhất.',
        ], 201);
    }

    // POST /api/v1/team-building — yêu cầu tổ chức sự kiện / team building.
    // Lưu chung vào hộp thư liên hệ, gắn source = team_building và giữ các
    // trường riêng (công ty, số người, ngày, địa điểm, ngân sách) trong meta.
    public function teamBuilding(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^[0-9\s+.-]{8,20}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'group_size' => ['nullable', 'string', 'max:100'],
            'event_date' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'budget' => ['nullable', 'string', 'max:100'],
            'package' => ['nullable', 'string', 'max:255'],   // tên gói khách đang xem
            'message' => ['nullable', 'string', 'max:2000'],
            // Ô bẫy chống bot — người thật không thấy nên luôn để trống
            'website' => ['nullable', 'size:0'],
        ], [
            'name.required' => 'Vui lòng nhập họ tên.',
            'phone.required' => 'Vui lòng nhập số điện thoại.',
            'phone.regex' => 'Số điện thoại không hợp lệ.',
            'website.size' => 'Yêu cầu không hợp lệ.',
        ]);

        $meta = collect([
            'company' => $data['company'] ?? null,
            'group_size' => $data['group_size'] ?? null,
            'event_date' => $data['event_date'] ?? null,
            'location' => $data['location'] ?? null,
            'budget' => $data['budget'] ?? null,
            'package' => $data['package'] ?? null,
        ])->filter()->all();

        $subject = ! empty($data['package'])
            ? 'Team Building — '.$data['package']
            : 'Yêu cầu Team Building / Sự kiện';

        // Nội dung tối thiểu để hàng trong hộp thư không trống khi khách bỏ trống
        // ô lời nhắn — vẫn thấy được nhu cầu qua các trường meta.
        $message = trim((string) ($data['message'] ?? ''));
        if ($message === '') {
            $message = 'Khách để lại yêu cầu tổ chức team building / sự kiện. Xem chi tiết ở các trường bên dưới.';
        }

        // Cùng số điện thoại + nội dung y hệt trong 5 phút coi là bấm nhầm hai lần
        $trung = ContactMessage::query()
            ->where('phone', $data['phone'])
            ->where('message', $message)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->exists();

        if (! $trung) {
            ContactMessage::create([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'subject' => $subject,
                'source' => 'team_building',
                'message' => $message,
                'meta' => $meta ?: null,
                'ip' => $request->ip(),
            ]);
        }

        return response()->json([
            'message' => 'Đã nhận yêu cầu. Bộ phận sự kiện sẽ liên hệ tư vấn và báo giá cho bạn trong thời gian sớm nhất.',
        ], 201);
    }
}
