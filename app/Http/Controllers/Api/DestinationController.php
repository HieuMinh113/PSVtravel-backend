<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Destination;

class DestinationController extends Controller
{
    // GET /api/v1/destinations?featured=1
    public function index()
    {
        $query = Destination::query()->dangHienThi();

        if (request()->boolean('featured')) {
            $query->where('is_featured', true);
        }

        return response()->json([
            'data' => $query->get()->map(fn ($d) => $this->the($d))->values(),
        ]);
    }

    // GET /api/v1/destinations/{slug}
    public function show(string $slug)
    {
        $d = Destination::query()->dangHienThi()->where('slug', $slug)->first();

        if (! $d) {
            return response()->json(['message' => 'Không tìm thấy điểm đến.'], 404);
        }

        return response()->json(['data' => array_merge($this->the($d), [
            'description' => $d->description,
            'category_slug' => $d->category_slug,
        ])]);
    }

    // GET /api/v1/destinations-slugs — cho generateStaticParams
    public function slugs()
    {
        return response()->json(Destination::query()->dangHienThi()->pluck('slug'));
    }

    private function the(Destination $d): array
    {
        return [
            'id' => $d->id,
            'name' => $d->name,
            'slug' => $d->slug,
            'image' => $this->anh($d->image),
            'summary' => $d->summary,
            'region' => $d->region,
            'category_slug' => $d->category_slug,
            'is_featured' => $d->is_featured,
        ];
    }

    private function anh(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return str_starts_with($path, 'http') ? $path : asset('storage/'.$path);
    }
}
