<?php

namespace App\Services;

use Illuminate\Support\Str;
use Modules\Guide\Models\Guide;

/**
 * Gợi ý SEO cho một bài cẩm nang — đúng các mục Ahrefs hay báo: ảnh thiếu mô
 * tả (alt), mô tả Google ngắn, link đi qua chuyển hướng / trỏ trang đã gỡ,
 * đường dẫn sai định dạng. Hiện đầu form sửa bài và cột SEO ở danh sách bài.
 */
class KiemTraSeoBai
{
    private const TOI_DA = 70;

    private const DUOI = ' | PSV Travel';

    /** Mô tả Google ngắn hơn mức này thì Ahrefs báo "Meta description too short" */
    public const MO_TA_TOI_THIEU = 110;

    /** @return list<array{muc: 'loi'|'goi_y', noi_dung: string}> */
    public static function goiY(Guide $g): array
    {
        $ds = [];
        $them = function (string $muc, string $noiDung) use (&$ds) {
            $ds[] = ['muc' => $muc, 'noi_dung' => $noiDung];
        };
        $html = (string) $g->content;

        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $g->slug)) {
            $them('loi', 'Đường dẫn "'.$g->slug.'" sai định dạng (có dấu / chữ hoa / khoảng trắng). Sửa thành chữ thường không dấu nối bằng gạch ngang, VD "'.Str::slug((string) $g->slug).'" — link cũ tự chuyển sang link mới.');
        }

        $tieuDe = self::tieuDeGoogle($g);
        if (mb_strlen($tieuDe) > self::TOI_DA) {
            $them('loi', 'Tiêu đề trên Google dài '.mb_strlen($tieuDe).' ký tự, Google sẽ cắt thành "…". Viết ô "Tiêu đề SEO" 50–60 ký tự.');
        }

        $moTa = mb_strlen(trim((string) $g->excerpt));
        if ($moTa < self::MO_TA_TOI_THIEU) {
            $them('goi_y', $moTa === 0
                ? 'Chưa có Tóm tắt — Google sẽ tự lấy một đoạn bất kỳ làm mô tả. Viết 120–160 ký tự nói bài giúp gì cho người đọc.'
                : "Tóm tắt (mô tả trên Google) mới {$moTa} ký tự — nên 120–160 ký tự. Website tạm nối thêm đoạn đầu bài cho đủ.");
        }

        preg_match_all('/<img\b[^>]*>/i', $html, $anh);
        $thieuAlt = count(array_filter($anh[0], fn ($the) => ! preg_match('/\salt=(["\'])\s*[^"\'\s][^"\']*\1/i', $the)));
        if ($thieuAlt > 0) {
            $them('goi_y', "{$thieuAlt}/".count($anh[0]).' ảnh trong bài chưa có mô tả (alt). Trong khung soạn bài: bấm vào ảnh → nút ghim "Đính kèm tệp" → điền "Văn bản thay thế" (tả ảnh có gì, VD "Phố Dotonbori Osaka về đêm").');
        }

        $link = SuaLinkNoiDung::sua($html);
        foreach (array_unique(array_column($link['hong'], 'link')) as $hong) {
            $them('loi', "Link trong bài tới trang không còn: {$hong} — đổi sang trang khác hoặc bỏ link.");
        }
        $doi = count($link['doi']);
        if ($doi > 0) {
            $them('goi_y', "{$doi} link trong bài đi vòng qua chuyển hướng (www, dấu / cuối, link web cũ). Bấm Lưu là tự sửa.");
        }

        if (blank($g->cover_image)) {
            $them('goi_y', 'Chưa có ảnh bìa — Google, Facebook, Zalo hiện ảnh này khi chia sẻ bài.');
        }
        if (blank($g->team_member_id)) {
            $them('goi_y', 'Chưa chọn "Tác giả hiện trên bài" — bài có người viết thật, có kinh nghiệm được Google đánh giá cao hơn.');
        }

        return $ds;
    }

    /** Tiêu đề Google như website hiện (tieuDeSeo trong app/lib/seo.js), trước khi cắt */
    public static function tieuDeGoogle(Guide $g): string
    {
        $goc = trim((string) preg_replace('/\s+/u', ' ', (string) ($g->seo_title ?: $g->title)));

        return mb_strlen($goc.self::DUOI) <= self::TOI_DA ? $goc.self::DUOI : $goc;
    }
}
