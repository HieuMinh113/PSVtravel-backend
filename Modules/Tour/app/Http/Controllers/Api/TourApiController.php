<?php

namespace Modules\Tour\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Tour\Models\Tour;
use Modules\Tour\Transformers\TourDetailResource;
use Modules\Tour\Transformers\TourListResource;

class TourApiController extends Controller
{
    // GET /api/v1/tours
    public function index(Request $request)
    {
        // Ẩn tour mà mọi đợt sắp tới đều đã quá hạn nộp visa; thẻ tour chỉ kể
        // các đợt còn đặt được (còn nhận, chưa đi, chưa quá hạn visa)
        $query = Tour::query()->hienChoKhach()
            ->with([
                'categories:id,slug',
                'departures' => fn ($q) => $q->hienTrenWeb()->orderBy('start_date'),
            ]);

        if (in_array($request->query('type'), ['domestic', 'abroad'], true)) {
            $query->where('type', $request->query('type'));
        }

        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        if ($slug = $request->query('category')) {
            $query->whereHas('categories', fn ($q) => $q->where('slug', $slug));
        }

        $tours = $query->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->paginate(min((int) $request->query('per_page', 12), 50));

        return TourListResource::collection($tours);
    }

    // GET /api/v1/tours/{slug}
    public function show(string $slug)
    {
        $tour = Tour::query()
            ->published()
            ->with(['images', 'itineraries', 'departures', 'categories', 'reviews'])
            ->where('slug', $slug)
            ->firstOrFail();

        return new TourDetailResource($tour);
    }

    // GET /api/v1/tours-sitemap — MỌI tour đang hiển thị cho sitemap.xml: không giới
    // hạn 50 như danh sách, và gồm cả tour tạm ẩn khỏi danh sách vì mọi đợt đã quá
    // hạn nộp visa (trang tour vẫn mở được, có đợt mới là bán lại).
    public function sitemap()
    {
        return response()->json([
            'data' => Tour::query()->published()->orderBy('id')->get(['slug', 'type', 'updated_at'])
                ->map(fn (Tour $t) => ['slug' => $t->slug, 'type' => $t->type, 'updated_at' => $t->updated_at?->toIso8601String()])
                ->values(),
        ]);
    }

    // GET /api/v1/tours-slugs  — cho generateStaticParams của Next
    public function slugs()
    {
        return response()->json(
            Tour::query()->published()->pluck('slug')
        );
    }
}
