<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TacGiaResource;
use App\Models\TeamMember;
use Illuminate\Support\Carbon;
use Modules\Guide\Transformers\GuideListResource;

/**
 * Tác giả bài cẩm nang — chỉ người đang hiển thị trong Đội ngũ và có ít nhất
 * một bài đã đăng (trang không có bài nào thì Google coi là trang mỏng).
 */
class TacGiaController extends Controller
{
    // GET /api/v1/authors — cho sitemap, dựng sẵn trang
    public function index()
    {
        return response()->json(['data' => TeamMember::query()->coTrangTacGia()
            ->withMax(['baiViet' => fn ($q) => $q->dangHienThi()], 'updated_at')
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->map(fn (TeamMember $m) => [
                'slug' => $m->slug,
                'name' => $m->name,
                // Trang đổi khi hồ sơ hoặc một bài của người đó đổi
                'updated_at' => collect([$m->updated_at, $m->bai_viet_max_updated_at ? Carbon::parse($m->bai_viet_max_updated_at) : null])
                    ->filter()->max()?->toIso8601String(),
            ])]);
    }

    // GET /api/v1/authors/{slug} — hồ sơ + các bài đã viết
    public function show(string $slug)
    {
        $m = TeamMember::query()->coTrangTacGia()->where('slug', $slug)->firstOrFail();
        $bai = $m->baiViet()->dangHienThi()->with('tacGia')
            ->orderByDesc('published_at')->orderByDesc('id')->limit(60)->get();

        return response()->json(['data' => [
            ...TacGiaResource::chiTiet($m),
            'guides' => GuideListResource::collection($bai)->resolve(),
        ]]);
    }
}
