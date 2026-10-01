<?php

namespace Modules\Tour\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Tour\Models\Tour;
use Modules\Tour\Models\TourQrScan;

/**
 * Khách quét mã QR → website (Next, đường /qr/{id}) gọi vào đây để ghi một
 * lượt quét và hỏi phải chuyển khách tới đâu.
 *
 * Trả về đường dẫn tương đối trên website:
 *  - tour đang bán → trang chi tiết tour;
 *  - tour đã ẩn / nháp / đã xoá → trang danh sách cùng loại (tờ rơi đã in thì
 *    khách vẫn vào được web, không gặp trang lỗi).
 */
class TourQrController extends Controller
{
    /** Trình duyệt tự động (xem trước link, máy tìm kiếm, công cụ) — không tính là lượt quét. */
    private const BOT = '/bot|crawl|spider|slurp|preview|facebookexternalhit|zalo.*(link|preview)|curl|wget|python|headless|lighthouse|go-http|java\//i';

    // POST /api/v1/qr/{id}
    public function quet(Request $request, int $id): JsonResponse
    {
        $tour = Tour::withTrashed()->find($id);
        if (! $tour) {
            return response()->json(['url' => '/'], 404);
        }

        $ua = (string) $request->userAgent();
        if ($ua !== '' && ! preg_match(self::BOT, $ua)) {
            // Một người quét lại nhiều lần liền nhau (mạng chập chờn, bấm lại)
            // chỉ tính một lượt trong 30 phút. Chỉ giữ mã băm tạm trong cache,
            // không lưu IP vào cơ sở dữ liệu.
            $khoa = 'qr-quet:'.$tour->getKey().':'.sha1($request->ip().'|'.$ua);
            if (Cache::add($khoa, 1, now()->addMinutes(30))) {
                TourQrScan::create([
                    'tour_id' => $tour->getKey(),
                    'thiet_bi' => preg_match('/Mobi|Android|iPhone|iPad|iPod/i', $ua) ? 'dien_thoai' : 'may_tinh',
                    'scanned_at' => now(),
                ]);
            }
        }

        $muc = $tour->type === 'abroad' ? '/tour-nuoc-ngoai' : '/tour-trong-nuoc';
        $dangBan = ! $tour->trashed() && $tour->status === 'published';

        return response()->json(['url' => $dangBan ? "{$muc}/{$tour->slug}" : $muc]);
    }
}
