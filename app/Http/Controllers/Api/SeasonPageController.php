<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SeasonPage;
use Modules\Tour\Transformers\TourListResource;

class SeasonPageController extends Controller
{
    // GET /api/v1/season-pages — trang theo mùa / dịp đang bật (sitemap, llms.txt)
    public function index()
    {
        return response()->json([
            'data' => SeasonPage::query()->dangHienThi()->get()
                ->map(fn (SeasonPage $p) => [
                    'slug' => $p->slug,
                    'name' => $p->name,
                    'start_from' => $p->start_from->format('Y-m-d'),
                    'start_to' => $p->start_to->format('Y-m-d'),
                    'updated_at' => $p->updated_at?->toIso8601String(),
                ])->values(),
        ]);
    }

    // GET /api/v1/season-pages/{slug} — nội dung trang + tour khởi hành trong dịp
    public function show(string $slug)
    {
        $p = SeasonPage::query()->dangHienThi()->where('slug', $slug)->first();
        if (! $p) {
            return response()->json(['message' => 'Không tìm thấy trang.'], 404);
        }

        return response()->json(['data' => [
            'slug' => $p->slug,
            'name' => $p->name,
            'type' => $p->type,
            'start_from' => $p->start_from->format('Y-m-d'),
            'start_to' => $p->start_to->format('Y-m-d'),
            'intro' => $p->intro,
            // Câu hỏi trống nội dung thì không gửi — hiện ra thành dòng rỗng
            'faqs' => collect($p->faqs ?? [])
                ->filter(fn ($f) => filled($f['q'] ?? null) && filled($f['a'] ?? null))
                ->map(fn ($f) => ['q' => $f['q'], 'a' => $f['a']])
                ->values(),
            'cover_image' => $p->cover_image
                ? (str_starts_with($p->cover_image, 'http') ? $p->cover_image : asset('storage/'.$p->cover_image))
                : null,
            'seo_title' => $p->seo_title,
            'seo_description' => $p->seo_description,
            'updated_at' => $p->updated_at?->toIso8601String(),
            'tours' => TourListResource::collection($p->truyVanTour()->limit(100)->get())->resolve(),
        ]]);
    }
}
