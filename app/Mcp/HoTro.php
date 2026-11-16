<?php

namespace App\Mcp;

use Illuminate\Support\Str;

/**
 * Hàm dùng chung cho các công cụ MCP: link sang website, bỏ thẻ HTML, so khớp
 * từ khoá không dấu ("da nang" khớp "Đà Nẵng").
 */
class HoTro
{
    /** Link trang trên website (psvtravel.com), không phải tên miền API */
    public static function web(string $duongDan = ''): string
    {
        $goc = rtrim((string) config('app.frontend_url'), '/');

        return $duongDan === '' ? $goc : $goc.'/'.ltrim($duongDan, '/');
    }

    public static function urlTour(string $slug, ?string $loai): string
    {
        return self::web(($loai === 'abroad' ? 'tour-nuoc-ngoai/' : 'tour-trong-nuoc/').$slug);
    }

    /** HTML → chữ thường một dòng, cắt gọn ở ranh giới từ */
    public static function chu(?string $html, int $toiDa = 600): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }
        $chu = preg_replace('/<(br|\/p|\/li|\/h[1-6]|\/div)\b[^>]*>/i', ' ', $html);
        $chu = html_entity_decode(strip_tags((string) $chu), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $chu = trim((string) preg_replace('/\s+/u', ' ', $chu));

        return $chu === '' ? null : Str::limit($chu, $toiDa, '…', preserveWords: true);
    }

    /**
     * Markdown (AI viết) → HTML cho ô nội dung bài. Bỏ mọi HTML viết tay trong
     * Markdown và link javascript:; tiêu đề # (H1) hạ thành H2 vì trang bài đã có
     * H1 là tên bài. Bảng theo kiểu GitHub vẫn giữ.
     */
    public static function htmlTuMarkdown(string $md): string
    {
        $html = Str::markdown($md, ['html_input' => 'strip', 'allow_unsafe_links' => false]);

        return trim((string) preg_replace(['#<h1(\s[^>]*)?>#i', '#</h1>#i'], ['<h2$1>', '</h2>'], $html));
    }

    /** Chuỗi để so khớp: chữ thường, bỏ dấu tiếng Việt */
    public static function khongDau(?string $s): string
    {
        return Str::lower(Str::ascii((string) $s));
    }

    // Từ người ta hay gõ kèm nhưng không có trong tên tour ("tour Hàn Quốc", "du lịch Đà Lạt")
    private const TU_BO = ['tour', 'du', 'lich', 'di', 'chuyen', 'trip'];

    /** Mọi từ có nghĩa trong từ khoá đều xuất hiện trong $chuoi (không phân biệt dấu) */
    public static function khop(?string $tuKhoa, string ...$chuoi): bool
    {
        $tu = array_diff(preg_split('/[\s,.;:\/\-–]+/u', self::khongDau($tuKhoa), -1, PREG_SPLIT_NO_EMPTY), self::TU_BO);
        if ($tu === []) {
            return true;
        }
        $noi = self::khongDau(implode(' ', $chuoi));

        foreach ($tu as $t) {
            if (! str_contains($noi, $t)) {
                return false;
            }
        }

        return true;
    }
}
