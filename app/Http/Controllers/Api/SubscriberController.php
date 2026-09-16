<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use Illuminate\Http\Request;

class SubscriberController extends Controller
{
    // POST /api/v1/subscribe — khách đăng ký nhận ưu đãi
    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'source' => ['nullable', 'string', 'max:100'],
        ]);

        $sub = Subscriber::withTrashed()->firstOrNew(['email' => $data['email']]);
        $sub->source = $data['source'] ?? 'website';
        $sub->ip = $request->ip();
        $sub->status = 'active';
        if ($sub->trashed()) {
            $sub->restore();
        }
        $sub->save();

        return response()->json(['message' => 'Đăng ký nhận ưu đãi thành công!']);
    }
}
