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
        $query = Tour::query()->published()
        ->with(['departures' => fn ($q) => $q
        ->where('status', 'open')
        ->whereDate('start_date', '>=', now())
        ->orderBy('start_date')]);

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

    // GET /api/v1/tours-slugs  — cho generateStaticParams của Next
    public function slugs()
    {
        return response()->json(
            Tour::query()->published()->pluck('slug')
        );
    }
}