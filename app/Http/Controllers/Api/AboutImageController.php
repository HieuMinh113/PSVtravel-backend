<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AboutImage;

class AboutImageController extends Controller
{
    // GET /api/v1/about-images — ảnh trang "Về chúng tôi", đã xuất bản, theo thứ tự
    public function index()
    {
        $items = AboutImage::query()->dangHienThi()->limit(30)->get();

        return response()->json([
            'data' => $items->map(fn ($m) => [
                'id' => $m->id,
                'image' => $this->anh($m->image),
                'caption' => $m->caption,
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
