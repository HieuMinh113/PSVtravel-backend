<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LinkGoogleMaps;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ô chọn vị trí (Bản đồ cẩm nang) gửi link Google Maps rút gọn lên đây để
 * lấy toạ độ — xem public/js/psv/chon-vi-tri.js và App\Services\LinkGoogleMaps.
 */
class DocLinkBanDoController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()->canAny(['Create:GuideCity', 'Update:GuideCity', 'Create:GuideSpot', 'Update:GuideSpot']), 403);

        $url = (string) $request->validate(['url' => ['required', 'string', 'max:2000']])['url'];

        if (! LinkGoogleMaps::laLinkGoogle($url)) {
            return response()->json(['message' => 'Chỉ đọc được link Google Maps (maps.app.goo.gl, google.com/maps…).'], 422);
        }

        $kq = LinkGoogleMaps::doc($url);
        if (! $kq) {
            return response()->json(['message' => 'Không lấy được toạ độ từ link này. Mở link, bấm chuột phải đúng chỗ trên Google Maps → bấm vào dòng số toạ độ đầu tiên (tự copy) rồi dán vào đây.'], 422);
        }

        return response()->json($kq)->header('Cache-Control', 'no-store');
    }
}
