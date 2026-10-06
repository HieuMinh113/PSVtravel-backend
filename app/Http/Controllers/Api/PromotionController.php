<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promotion;

class PromotionController extends Controller
{
    // GET /api/v1/promotions — ưu đãi đang hiển thị, còn hạn
    public function index()
    {
        $items = Promotion::query()->dangHienThi()->limit(30)->get();

        return response()->json([
            'data' => $items->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'description' => $p->description,
                'image' => $this->anh($p->image),
                'discount_label' => $p->discount_label,
                'price' => $p->price,
                'old_price' => $p->old_price,
                'link_url' => $p->link_url,
                'badge' => $p->badge,
                'ends_at' => $p->ends_at?->format('Y-m-d'),
            ])->values(),
        ]);
    }

    private function anh(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return str_starts_with($path, 'http') ? $path : asset('storage/'.$path);
    }
}
