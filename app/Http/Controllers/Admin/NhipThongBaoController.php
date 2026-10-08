<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Navigation\NavigationManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;

/**
 * "Nhịp" của trang quản trị — trình duyệt hỏi mỗi 10 giây (public/js/psv/thong-bao.js):
 *  - số thông báo chưa đọc + vài thông báo mới nhất → phát tiếng, hiện popup,
 *    làm mới chuông ngay
 *  - con số trên menu (Duyệt khoản thu, Đơn đặt tour, Bảng chấm công…) →
 *    cập nhật tại chỗ, khỏi phải F5
 *
 * Không có websocket (Reverb / Pusher) trên máy chủ nên dùng hỏi định kỳ —
 * gọn, không thêm dịch vụ phải trông.
 */
class NhipThongBaoController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $u = $request->user();

        $moi = $u->unreadNotifications()
            ->where('created_at', '>=', now()->subMinutes(10))
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'title' => self::chu($n->data['title'] ?? ''),
                'body' => self::chu($n->data['body'] ?? ''),
                'status' => $n->data['status'] ?? null,
                'url' => collect($n->data['actions'] ?? [])->pluck('url')->filter()->first(),
            ])
            ->values();

        return response()->json([
            'chua_doc' => $u->unreadNotifications()->count(),
            'moi' => $moi,
            'menu' => $this->soTrenMenu(),
        ])->header('Cache-Control', 'no-store');
    }

    /** @return list<array{url: string, html: ?string}> */
    private function soTrenMenu(): array
    {
        // Menu được dựng một lần rồi giữ trong bộ nhớ — bỏ bản cũ để đếm lại số mới
        app()->forgetInstance(NavigationManager::class);
        $ds = [];
        foreach (Filament::getNavigation() as $nhom) {
            $muc = $nhom instanceof NavigationGroup ? $nhom->getItems() : [];
            foreach ($muc as $item) {
                foreach ([$item, ...collect($item->getChildItems())->all()] as $x) {
                    /** @var NavigationItem $x */
                    if (! $x->getUrl()) {
                        continue;
                    }
                    $so = $x->getBadge();
                    $ds[] = [
                        'url' => $x->getUrl(),
                        'html' => filled($so) ? Blade::render(
                            '<x-filament::badge :color="$mau" :tooltip="$goiY">{{ $so }}</x-filament::badge>',
                            ['mau' => $x->getBadgeColor($so), 'goiY' => $x->getBadgeTooltip(), 'so' => $so],
                        ) : null,
                    ];
                }
            }
        }

        return $ds;
    }

    private static function chu(?string $html): string
    {
        $chu = preg_replace('#<br\s*/?>#i', ' · ', (string) $html);

        return trim(html_entity_decode(strip_tags($chu)));
    }
}
