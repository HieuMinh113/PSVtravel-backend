<?php

namespace Modules\Review\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Review\Models\Review;
use Modules\Review\Transformers\ReviewResource;

class ReviewApiController extends Controller
{
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
}