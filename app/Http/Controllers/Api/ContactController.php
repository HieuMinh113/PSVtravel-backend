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
}
