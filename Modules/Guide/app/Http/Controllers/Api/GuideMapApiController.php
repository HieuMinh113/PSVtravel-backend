<?php

namespace Modules\Guide\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Guide\Models\Guide;
use Modules\Guide\Models\GuideCity;
use Modules\Guide\Models\GuideCountry;
use Modules\Guide\Models\GuideSpot;

/**
 * GET /api/v1/guide-map — toàn bộ dữ liệu bản đồ cẩm nang trong một lần gọi
 * (vài chục nước × vài chục điểm vẫn chỉ vài chục KB): nước kèm hình dạng để
 * tô trên quả địa cầu, thành phố, điểm và bài cẩm nang gắn với từng điểm.
 *
 * Chỉ trả nước có ít nhất một thành phố đang hiện; bài cẩm nang nháp / ẩn /
 * hẹn giờ chưa tới thì điểm vẫn hiện nhưng không có nút đọc bài.
 */
class GuideMapApiController extends Controller
{
    public function index(): JsonResponse
    {
        $bai = fn ($q) => $q->dangHienThi()->select('id', 'slug', 'title', 'excerpt', 'cover_image');

        $nuoc = GuideCountry::query()
            ->dangHienThi()
            ->with(['cities' => fn ($q) => $q->dangHienThi()->with([
                'guide' => $bai,
                'spots' => fn ($q) => $q->dangHienThi()->with(['guide' => $bai]),
            ])])
            ->get()
            ->filter(fn (GuideCountry $n) => $n->cities->isNotEmpty())
            ->map(fn (GuideCountry $n) => $this->nuoc($n))
            ->filter()
            ->values();

        return response()->json(['data' => [
            'countries' => $nuoc,
            'types' => GuideSpot::LOAI,
        ]]);
    }

    private function nuoc(GuideCountry $n): ?array
    {
        $ranhGioi = GuideCountry::duLieuNuoc($n->code);
        if (! $ranhGioi) {
            return null;
        }

        // Khung để bay tới: khung phần đất chính của nước, nới ra cho đủ các thành phố
        [$w, $s, $e, $b] = $ranhGioi['khung'];
        foreach ($n->cities as $tp) {
            $w = min($w, $tp->lng);
            $e = max($e, $tp->lng);
            $s = min($s, $tp->lat);
            $b = max($b, $tp->lat);
        }

        return [
            'code' => $n->code,
            'name' => $n->name,
            'slug' => $n->slug,
            'color' => $n->mau(), // màu tô trên quả địa cầu (admin chọn, mặc định xanh lá)
            'intro' => $n->intro,
            'cover_image' => self::anh($n->cover_image),
            'label' => $ranhGioi['nhan'],
            'bbox' => [$w, $s, $e, $b],
            'geometry' => $ranhGioi['hinh'],
            'cities' => $n->cities->map(fn (GuideCity $tp) => [
                'name' => $tp->name,
                'slug' => $tp->slug,
                'lat' => $tp->lat,
                'lng' => $tp->lng,
                'zoom' => $tp->zoom,
                'intro' => $tp->intro,
                'cover_image' => self::anh($tp->cover_image),
                'guide' => self::bai($tp->guide),
                'spots' => $tp->spots->map(fn (GuideSpot $d) => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'type' => $d->type,
                    'lat' => $d->lat,
                    'lng' => $d->lng,
                    'summary' => $d->summary,
                    'image' => self::anh($d->image) ?? self::anh($d->guide?->cover_image),
                    'guide' => self::bai($d->guide),
                ])->values(),
            ])->values(),
        ];
    }

    private static function bai(?Guide $g): ?array
    {
        return $g ? [
            'slug' => $g->slug,
            'title' => $g->title,
            'excerpt' => $g->excerpt,
            'cover_image' => self::anh($g->cover_image),
        ] : null;
    }

    private static function anh(?string $duong): ?string
    {
        if (! $duong) {
            return null;
        }

        return str_starts_with($duong, 'http') ? $duong : asset('storage/'.$duong);
    }
}
