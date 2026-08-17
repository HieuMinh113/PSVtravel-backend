<?php

namespace Modules\Guide\Http\Controllers\Api;

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

        // Tăng lượt xem — không ghi vào nhật ký (view_count không nằm trong logOnly)
        $guide->increment('view_count');

        return new GuideDetailResource($guide);
    }

    // GET /api/v1/guides-slugs — cho generateStaticParams của Next
    public function slugs()
    {
        return response()->json(
            Guide::query()->dangHienThi()->pluck('slug')
        );
    }
}