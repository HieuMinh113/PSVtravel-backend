<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Lấy toạ độ từ link Google Maps — kể cả link rút gọn "maps.app.goo.gl/…"
 * mà nút Chia sẻ của Google Maps tạo ra.
 *
 * Link rút gọn không chứa toạ độ, trình duyệt cũng không tự mở được (Google
 * chặn đọc chéo trang) nên máy chủ đi theo link giùm: chỉ đi theo các bước
 * chuyển hướng tới tên miền Google (không bị lợi dụng để gọi vào mạng nội bộ),
 * rồi đọc toạ độ trên link cuối cùng, không có thì đọc trong trang.
 */
class LinkGoogleMaps
{
    private const HOST = '/^(maps\.app\.goo\.gl|goo\.gl|(www\.|maps\.)?google\.[a-z]{2,3}(\.[a-z]{2})?)$/i';

    private const SO_LAN_CHUYEN = 6;

    private const TOI_DA_TRANG = 3_000_000;

    /** Link có phải của Google Maps không (https, tên miền Google, không cổng lạ, không user:pass). */
    public static function laLinkGoogle(string $url): bool
    {
        $p = parse_url(trim($url));
        if (! $p || strtolower($p['scheme'] ?? '') !== 'https' || empty($p['host'])
            || isset($p['user']) || isset($p['pass']) || (isset($p['port']) && (int) $p['port'] !== 443)) {
            return false;
        }
        $host = strtolower($p['host']);
        if (! preg_match(self::HOST, $host)) {
            return false;
        }

        // goo.gl trần là dịch vụ rút gọn chung (đã đóng) — chỉ nhận goo.gl/maps/…
        return $host !== 'goo.gl' || str_starts_with($p['path'] ?? '', '/maps');
    }

    /**
     * Đọc toạ độ có sẵn trong chữ (link đầy đủ hoặc "lat, lng").
     *
     * @return array{lat: float, lng: float, zoom: float|null}|null
     */
    public static function toaDoTrongLink(string $chu): ?array
    {
        $chu = rawurldecode(trim($chu));
        $mau = [
            '/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/',                          // vị trí chính xác của địa điểm
            '#/maps/(?:search|place)/(-?\d+\.\d+),\s*\+?(-?\d+\.\d+)#',             // /maps/search/16.05,+108.2
            '/[?&#](?:q|query|ll|destination|daddr|center)=(-?\d+\.\d+)(?:,|\s|\+)+(-?\d+\.\d+)/i',
            '/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)(?:,(\d+(?:\.\d+)?)z)?/',        // tâm khung nhìn
            '/^\(?\s*(-?\d+(?:\.\d+)?)\s*[,;\s]\s*(-?\d+(?:\.\d+)?)\s*\)?$/',      // "16.0544, 108.2022"
        ];
        foreach ($mau as $re) {
            if (preg_match($re, $chu, $m) && ($kq = self::hopLe($m[1], $m[2], $m[3] ?? null))) {
                return $kq;
            }
        }

        return null;
    }

    /**
     * Đi theo link (rút gọn) tới trang Google Maps và lấy toạ độ.
     *
     * @return array{lat: float, lng: float, zoom: float|null, ten: string|null}|null
     */
    public static function doc(string $url): ?array
    {
        $url = trim($url);
        if (! self::laLinkGoogle($url)) {
            return null;
        }

        $body = '';
        for ($i = 0; $i <= self::SO_LAN_CHUYEN; $i++) {
            if ($kq = self::toaDoTrongLink($url)) {
                return $kq + ['ten' => self::tenDiaDiem($url)];
            }
            if ($i === self::SO_LAN_CHUYEN) {
                return null;
            }

            try {
                $r = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0 Safari/537.36',
                    'Accept-Language' => 'vi,en;q=0.8',
                ])
                    ->withoutRedirecting()
                    ->withOptions(['stream' => true])
                    ->connectTimeout(5)
                    ->timeout(10)
                    ->get($url);
            } catch (Throwable) {
                return null;
            }

            $toi = $r->header('Location');
            if ($r->redirect() && $toi !== '') {
                $toi = self::duongDanTuyetDoi($url, $toi);
                if (! self::laLinkGoogle($toi)) {
                    return null; // chuyển sang nơi khác (trang đồng ý cookie, trang lạ…) — dừng
                }
                $url = $toi;

                continue;
            }
            if (! $r->successful()) {
                return null;
            }
            $body = self::docThan($r->toPsrResponse()->getBody());
            break;
        }

        $kq = self::toaDoTrongTrang($body);

        return $kq ? $kq + ['ten' => self::tenDiaDiem($url)] : null;
    }

    /**
     * Trang Google Maps (khi link cuối không có toạ độ, VD "?q=Tên&ftid=…")
     * vẫn ghi toạ độ trong ảnh xem trước og:image và trạng thái khởi tạo.
     */
    public static function toaDoTrongTrang(string $html): ?array
    {
        $html = str_replace(['&amp;', '\\u0026', '\\u003d'], ['&', '&', '='], $html);
        $mau = [
            '/[?&]markers=(-?\d+\.\d+)(?:%2C|,)(-?\d+\.\d+)/i',                    // ghim của địa điểm
            '/staticmap\?center=(-?\d+\.\d+)(?:%2C|,)(-?\d+\.\d+)/i',              // tâm ảnh xem trước
        ];
        foreach ($mau as $re) {
            if (preg_match($re, $html, $m) && ($kq = self::hopLe($m[1], $m[2]))) {
                return $kq;
            }
        }
        // APP_INITIALIZATION_STATE=[[[khoảng cách, KINH ĐỘ, VĨ ĐỘ], …
        if (preg_match('/APP_INITIALIZATION_STATE=\[\[\[[-\d.eE+]+,(-?\d+\.\d+),(-?\d+\.\d+)\]/', $html, $m)
            && ($kq = self::hopLe($m[2], $m[1]))) {
            return $kq;
        }
        // Trang chuyển tiếp (meta refresh / og:url) có ghi link Google Maps đầy đủ
        preg_match_all('#https://(?:www\.|maps\.)?google\.[a-z.]{2,6}/maps[^"\'\s<>]*#i', $html, $ds);
        foreach (array_slice($ds[0], 0, 20) as $link) {
            if ($kq = self::toaDoTrongLink($link)) {
                return $kq;
            }
        }

        return null;
    }

    private static function hopLe(string $lat, string $lng, ?string $zoom = null): ?array
    {
        $a = (float) $lat;
        $b = (float) $lng;
        if (abs($a) > 90 || abs($b) > 180 || ($a == 0 && $b == 0)) {
            return null;
        }

        return ['lat' => round($a, 7), 'lng' => round($b, 7), 'zoom' => $zoom !== null && $zoom !== '' ? (float) $zoom : null];
    }

    /** Tên địa điểm trong link (/maps/place/Tên/… hoặc ?q=Tên) — để báo lại cho người dùng. */
    private static function tenDiaDiem(string $url): ?string
    {
        if (preg_match('#/maps/place/([^/@?]+)#', $url, $m)) {
            $ten = $m[1];
        } elseif (preg_match('/[?&]q=([^&]+)/', $url, $m)) {
            $ten = $m[1];
        } else {
            return null;
        }
        $ten = trim(urldecode(str_replace('+', ' ', $ten)));
        if ($ten === '' || preg_match('/^-?\d+\.\d+,\s*-?\d+\.\d+$/', $ten)) {
            return null;
        }

        return mb_substr(trim(explode(',', $ten)[0]), 0, 120);
    }

    private static function duongDanTuyetDoi(string $goc, string $toi): string
    {
        if (preg_match('#^https?://#i', $toi)) {
            return $toi;
        }
        $p = parse_url($goc);
        $dau = $p['scheme'].'://'.$p['host'];

        return str_starts_with($toi, '/') ? $dau.$toi : $dau.'/'.$toi;
    }

    private static function docThan($stream): string
    {
        $than = '';
        while (! $stream->eof() && strlen($than) < self::TOI_DA_TRANG) {
            $than .= $stream->read(65536);
        }

        return $than;
    }
}
