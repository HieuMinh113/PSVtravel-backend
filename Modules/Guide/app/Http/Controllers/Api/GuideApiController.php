<?php

namespace Modules\Guide\Http\Controllers\Api;

use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Guide\Models\Guide;
use Modules\Guide\Transformers\GuideDetailResource;
use Modules\Guide\Transformers\GuideListResource;

class GuideApiController extends Controller
{
    // GET /api/v1/guides?category=kinh-nghiem
    public function index(Request $request)
    {
        $query = Guide::query()
            ->dangHienThi()
            ->with('author:id,name');

        if ($cat = $request->query('category')) {
            $query->where('category', $cat);
        }

        $guides = $query->orderByDesc('published_at')
            ->orderBy('sort_order')
            ->paginate(min((int) $request->query('per_page', 9), 30));

        return GuideListResource::collection($guides);
    }

    // GET /api/v1/guides/{slug}
    public function show(string $slug)
    {
        $guide = Guide::query()
            ->dangHienThi()
            ->with('author:id,name')
            ->where('slug', $slug)
            ->firstOrFail();

        // KHÔNG tăng lượt xem ở đây. Next.js dựng sẵn trang này và giữ bản
        // dựng trong 60 giây (ISR), nên 3 người mở bài trong cùng một phút thì
        // API chỉ được gọi đúng 1 lần — đếm ở đây là đếm số lần dựng lại trang,
        // không phải số lượt người đọc. Việc đếm chuyển sang ghiNhanLuotXem()
        // do trình duyệt gọi riêng.
        return new GuideDetailResource($guide);
    }

    // POST /api/v1/guides/{slug}/view — trình duyệt gọi khi bài thực sự mở ra
    public function ghiNhanLuotXem(Request $request, string $slug)
    {
        $guide = Guide::query()->dangHienThi()->where('slug', $slug)->first();

        if (! $guide) {
            return response()->json(['message' => 'Không tìm thấy bài viết.'], 404);
        }

        // Một người đọc lại bài trong vòng 1 giờ chỉ tính 1 lượt — tránh việc
        // bấm F5 liên tục thổi phồng con số.
        $khoa = 'luot-xem:'.$guide->id.':'.sha1((string) $request->ip());

        if (Cache::add($khoa, true, now()->addHour())) {
            $guide->increment('view_count');
        }

        return response()->json(['view_count' => $guide->view_count]);
    }

    // GET /api/v1/guides-slugs — cho generateStaticParams của Next
    public function slugs()
    {
        return response()->json(
            Guide::query()->dangHienThi()->pluck('slug')
        );
    }
}