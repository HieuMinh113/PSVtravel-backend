<?php

namespace Modules\Guide\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
            ->with(['author:id,name', 'tacGia']);

        if ($cat = $request->query('category')) {
            $query->where('category', $cat);
        }

        // Mới nhất lên đầu; bài chưa có ngày (nếu còn) xếp cuối — PostgreSQL mặc
        // định đưa ô trống lên ĐẦU khi xếp giảm dần. id: thứ tự cố định giữa các
        // bài cùng ngày. Trang /cam-nang lấy hết (tối đa 200) để không sót bài —
        // trước đây tối đa 30, có 55 bài thì 25 bài không hiện.
        $guides = $query->orderByRaw('CASE WHEN published_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('published_at')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate(max(1, min((int) $request->query('per_page', 9), 200)));

        return GuideListResource::collection($guides);
    }

    // GET /api/v1/guides/{slug}
    public function show(string $slug)
    {
        $guide = Guide::query()
            ->dangHienThi()
            ->with(['author:id,name', 'tacGia', 'tour.departures', 'spots.city.country', 'mapCities.country'])
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

        // Một người đọc lại bài trong vòng N phút chỉ tính 1 lượt — tránh việc
        // bấm F5 liên tục thổi phồng con số. Ba người KHÁC NHAU cùng đọc thì
        // vẫn tính đủ 3, vì khoá tính theo địa chỉ IP của từng người.
        //
        // Đặt LUOT_XEM_CACH_NHAU_PHUT=0 trong .env để tắt hẳn khi cần kiểm thử
        // (một máy bấm nhiều lần vẫn tính từng lượt).
        $cachNhauPhut = (int) config('guide.luot_xem_cach_nhau_phut', 60);

        if ($cachNhauPhut < 1) {
            $guide->increment('view_count');

            return response()->json(['ok' => true]); // không trả số lượt xem — chỉ admin xem
        }

        $khoa = 'luot-xem:'.$guide->id.':'.sha1((string) $request->ip());

        if (Cache::add($khoa, true, now()->addMinutes($cachNhauPhut))) {
            $guide->increment('view_count');
        }

        return response()->json(['ok' => true]); // không trả số lượt xem — chỉ admin xem
    }

    // GET /api/v1/guides-sitemap — MỌI bài đang hiển thị cho sitemap.xml (danh sách
    // bài chỉ trả tối đa 30 bài mỗi trang, sitemap sẽ sót bài cũ)
    public function sitemap()
    {
        return response()->json([
            'data' => Guide::query()->dangHienThi()->orderBy('id')->get(['slug', 'updated_at'])
                ->map(fn (Guide $g) => ['slug' => $g->slug, 'updated_at' => $g->updated_at?->toIso8601String()])
                ->values(),
        ]);
    }

    // GET /api/v1/guides-slugs — cho generateStaticParams của Next
    public function slugs()
    {
        return response()->json(
            Guide::query()->dangHienThi()->pluck('slug')
        );
    }
}
