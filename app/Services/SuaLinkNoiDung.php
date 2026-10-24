<?php

namespace App\Services;

use App\Models\Redirect;

/**
 * Link nhân viên chèn trong nội dung bài trỏ về chính website. Ahrefs báo
 * "Page has links to redirect" khi link đi vòng qua một lượt chuyển hướng:
 * dán nguyên địa chỉ www / psvtravel.com.vn, có dấu "/" cuối, hoặc trỏ link web
 * cũ (/tour_nuoc_ngoai/…) nay đã chuyển sang trang mới. Ở đây đưa link về
 * đường dẫn tương đối và trỏ thẳng trang đích.
 *
 * Link tới trang đã gỡ / chưa có trang thay thế thì không tự sửa được — báo
 * lại để nhân viên xem (khung Gợi ý SEO của bài, lệnh psv:sua-link-noi-dung).
 */
class SuaLinkNoiDung
{
    private const HOST = ['psvtravel.com', 'www.psvtravel.com', 'psvtravel.com.vn', 'www.psvtravel.com.vn'];

    // Tiền tố đường dẫn của web cũ — hỏi bộ khớp link cũ (giống app/lib/linkCu.js bên website)
    private const TIEN_TO_CU = ['tour_nuoc_ngoai', 'tour_trong_nuoc', 'tour', 'product', 'san-pham', 'product-category',
        'danh-muc-san-pham', 'danh-muc', 'category', 'wp-content', 'wp-includes', 'wp-admin', 'wp-json'];

    /**
     * @return array{html: string, doi: list<array{cu: string, moi: string}>, hong: list<array{link: string, ma: int}>}
     */
    public static function sua(?string $html): array
    {
        $doi = $hong = [];
        $ra = preg_replace_callback('/(<a\b[^>]*?\shref=)(["\'])(.*?)\2/is', function ($m) use (&$doi, &$hong) {
            $href = html_entity_decode($m[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $noiBo = self::noiBo($href);
            if ($noiBo === null) {
                return $m[0];
            }
            [$duongDan, $duoi] = $noiBo;
            [$dich, $ma] = self::dich($duongDan);
            if ($ma === 404 || $ma === 410) {
                $hong[] = ['link' => $duongDan, 'ma' => $ma];
            }
            $moi = $dich.$duoi;
            if ($moi === $href) {
                return $m[0];
            }
            $doi[] = ['cu' => $href, 'moi' => $moi];

            return $m[1].$m[2].htmlspecialchars($moi, ENT_QUOTES | ENT_HTML5, 'UTF-8', false).$m[2];
        }, (string) $html);

        return ['html' => $ra ?? (string) $html, 'doi' => $doi, 'hong' => $hong];
    }

    /** @return array{0: string, 1: string}|null [đường dẫn không có "/" cuối, phần ?query#neo]; null = link ra ngoài */
    private static function noiBo(string $href): ?array
    {
        $href = trim($href);
        if ($href === '' || str_starts_with($href, '#')) {
            return null;
        }
        if (preg_match('#^(https?:)?//#i', $href)) {
            $host = strtolower((string) parse_url(str_starts_with($href, '//') ? 'https:'.$href : $href, PHP_URL_HOST));
            if (! in_array($host, self::HOST, true)) {
                return null;
            }
            $href = preg_replace('#^(https?:)?//[^/?\#]+#i', '', $href);
        } elseif (! str_starts_with($href, '/')) {
            return null; // mailto:, tel:, đường dẫn tương đối lạ
        }

        preg_match('/^([^?#]*)(.*)$/s', $href, $p);
        $duongDan = rtrim($p[1], '/') ?: '/';

        return [$duongDan, $p[2]];
    }

    /**
     * Trang đích cuối cùng của một đường dẫn nội bộ: [đường dẫn, mã]. Mã 200 =
     * để nguyên, 301 = đổi sang trang khác, 410 = đã gỡ, 404 = link cũ chưa có
     * trang thay thế.
     *
     * @return array{0: string, 1: int}
     */
    public static function dich(string $duongDan): array
    {
        $p = $duongDan;
        $ma = 200;
        for ($i = 0; $i < 5; $i++) {
            $dong = Redirect::query()->whereIn('old_path', array_unique([$p, mb_strtolower($p)]))->first();
            if ($dong) {
                if ($dong->status_code === 301 && $dong->new_path) {
                    [$p, $ma] = [$dong->new_path, 301];

                    continue;
                }

                return [$p, $dong->status_code === 410 ? 410 : 404];
            }
            $dau = explode('/', ltrim(strtolower($p), '/'))[0];
            if (! in_array($dau, self::TIEN_TO_CU, true)) {
                break;
            }
            // Link web cũ chưa ai mở tới: hỏi bộ khớp (không ghi lượt mở)
            [$kq] = LinkCu::xem($p);
            if ($kq['ma'] === 301 && $kq['toi']) {
                [$p, $ma] = [$kq['toi'], 301];

                continue;
            }

            return [$p, $kq['ma'] === 410 ? 410 : 404];
        }

        return [$p, $ma];
    }
}
