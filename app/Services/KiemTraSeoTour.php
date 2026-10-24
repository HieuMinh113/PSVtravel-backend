<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Tour\Models\Tour;

/**
 * Gợi ý SEO cho một tour, hiện ngay trong form tour và cột "SEO" của danh
 * sách — nhân viên thấy lúc nhập thay vì chờ IT chạy lệnh kiểm tra.
 *
 * Tiêu đề Google tính GIỐNG HỆT website (frontend app/lib/seo.js: tieuDeTour +
 * tieuDeSeo): tên tour + số ngày nếu tên chưa có + "| Từ 3,99 triệu" nếu còn
 * chỗ, tối đa 70 ký tự. Đổi cách tính bên đó thì đổi cả ở đây.
 *
 * Mỗi gợi ý: ['muc' => 'loi' (nên sửa ngay) | 'goi_y' (nên có), 'noi_dung' => …]
 */
class KiemTraSeoTour
{
    private const TOI_DA = 70;

    private const DUOI = ' | PSV Travel';

    /** @return list<array{muc: string, noi_dung: string}> */
    public static function goiY(Tour $t): array
    {
        $ds = [];
        $loi = function (string $muc, string $noiDung) use (&$ds) {
            $ds[] = ['muc' => $muc, 'noi_dung' => $noiDung];
        };

        $tieuDe = self::tieuDeGoogle($t);
        if (mb_strlen($tieuDe) > self::TOI_DA) {
            $loi('loi', 'Tiêu đề trên Google dài '.mb_strlen($tieuDe).' ký tự, Google sẽ cắt thành "…". Viết ô "Tiêu đề SEO" 50–60 ký tự, tên điểm đến đặt đầu.');
        }
        $trung = self::tieuDeTourKhac()->filter(fn ($x) => $x['id'] !== $t->getKey() && $x['tieu_de'] === mb_strtolower(self::tieuDeGoc($t)))->first();
        if ($trung && $t->status === 'published') {
            $loi('loi', 'Tiêu đề Google trùng tour "'.$trung['ten'].'" — Google khó phân biệt hai trang. Viết "Tiêu đề SEO" riêng (thêm nơi khởi hành / số ngày / điểm nhấn).');
        }
        if ((int) $t->adult_price <= 0) {
            $loi('loi', 'Chưa có giá người lớn: Google không hiện giá, tiêu đề không có "Từ … triệu".');
        }
        if ($t->relationLoaded('itineraries') ? $t->itineraries->isEmpty() : ! $t->itineraries()->exists()) {
            $loi('loi', 'Chưa có lịch trình từng ngày — phần nội dung dày nhất của trang tour.');
        }

        $ten = (string) $t->name;
        if (! self::tenCoSoNgay($t)) {
            $loi('goi_y', 'Tên tour (tiêu đề lớn trên trang) chưa có số ngày, VD "Đà Nẵng – Hội An 3N2Đ".');
        }
        if (! self::tenCoNoi($t)) {
            $loi('goi_y', 'Tên tour chưa có tên điểm đến (nước / thành phố) — khách tìm "tour + nơi đến" trên Google.');
        }
        foreach (self::chuDinhLien($t) as $tu => $goiY) {
            $loi('goi_y', "Đường dẫn có chữ dính liền \"{$tu}\" — nên tách \"{$goiY}\" (đổi xong link cũ tự chuyển sang link mới).");
        }
        if (strlen((string) $t->slug) > 75) {
            $loi('goi_y', 'Đường dẫn dài '.strlen((string) $t->slug).' ký tự — nên gọn dưới 75 (bỏ chữ thừa như "tour", "du-lich", năm).');
        }
        if (blank($t->cover_image)) {
            $loi('goi_y', 'Thiếu ảnh bìa: chia sẻ Facebook / Zalo hiện logo thay ảnh tour.');
        }
        if (blank($t->departure_from)) {
            $loi('goi_y', 'Thiếu "Khởi hành từ": mô tả trên Google thiếu nơi đi.');
        }
        if (empty(array_filter((array) $t->highlights))) {
            $loi('goi_y', 'Thiếu điểm nổi bật (3–5 ý): mô tả trên Google ngắn, kém hấp dẫn.');
        }
        if (empty(array_filter((array) $t->included)) || empty(array_filter((array) $t->excluded))) {
            $loi('goi_y', 'Thiếu "Giá tour bao gồm" hoặc "Không bao gồm" — khách hay so sánh mục này giữa các công ty.');
        }
        if (blank($t->cancellation_policy)) {
            $loi('goi_y', 'Thiếu chính sách huỷ: câu hỏi thường gặp dùng câu trả lời chung.');
        }

        return $ds;
    }

    /** Tiêu đề thẻ <title> website sẽ dùng (chưa cắt) */
    public static function tieuDeGoogle(Tour $t): string
    {
        $goc = self::tieuDeGoc($t);

        return mb_strlen($goc.self::DUOI) <= self::TOI_DA ? $goc.self::DUOI : $goc;
    }

    /** Tiêu đề trước khi thêm đuôi " | PSV Travel" */
    private static function tieuDeGoc(Tour $t): string
    {
        if (filled($t->seo_title)) {
            return trim(preg_replace('/\s+/u', ' ', (string) $t->seo_title));
        }
        $soNgay = '';
        if ($t->duration_days && ! self::tenCoSoNgayWeb($t)) {
            $soNgay = (int) $t->duration_nights > 0 ? " {$t->duration_days}N{$t->duration_nights}Đ" : " {$t->duration_days} ngày";
        }
        $ten = trim(preg_replace('/\s+/u', ' ', (string) $t->name)).$soNgay;
        $gia = (int) $t->adult_price;
        if ($gia <= 0) {
            return $ten;
        }
        $giaGon = $gia >= 1_000_000
            ? rtrim(rtrim(number_format($gia / 1_000_000, 2, ',', '.'), '0'), ',').' triệu'
            : number_format($gia, 0, ',', '.').'đ';
        $coGia = "{$ten} | Từ {$giaGon}";

        return mb_strlen($coGia) <= 65 ? $coGia : $ten;
    }

    /** Đúng như website (tenCoSoNgay trong tomTatTour.js) — quyết định có ghép số ngày vào tiêu đề */
    private static function tenCoSoNgayWeb(Tour $t): bool
    {
        $ten = (string) $t->name;

        return ($t->duration_days && str_contains($ten, "{$t->duration_days} ngày {$t->duration_nights} đêm"))
            || (bool) preg_match('/\d+\s*N\s*\d+\s*Đ/iu', $ten);
    }

    /** Tên có nói số ngày theo cách nào đó ("5 ngày", "4N3Đ") — gợi ý cho tiêu đề lớn trên trang */
    private static function tenCoSoNgay(Tour $t): bool
    {
        return self::tenCoSoNgayWeb($t) || (bool) preg_match('/\d+\s*ngày/iu', (string) $t->name);
    }

    private static function tenCoNoi(Tour $t): bool
    {
        $ten = Str::slug((string) $t->name);
        if (LinkCu::coTenNoi($ten)) {
            return true;
        }
        foreach ([$t->country, $t->region, ...$t->categories->pluck('name')->all()] as $noi) {
            if (filled($noi) && str_contains("-{$ten}-", '-'.Str::slug((string) $noi).'-')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Chữ trong đường dẫn ghép liền nhiều tiếng của tên tour: "thanhdo" ← "Thành Đô"
     *
     * @return array<string, string> chữ dính → cách tách đúng
     */
    private static function chuDinhLien(Tour $t): array
    {
        $tuTen = array_values(array_filter(explode('-', Str::slug((string) $t->name))));
        $kq = [];
        foreach (array_filter(explode('-', (string) $t->slug)) as $tu) {
            if (strlen($tu) < 6) {
                continue;
            }
            for ($i = 0; $i < count($tuTen); $i++) {
                for ($dai = 2; $dai <= 4 && $i + $dai <= count($tuTen); $dai++) {
                    if (implode('', array_slice($tuTen, $i, $dai)) === $tu) {
                        $kq[$tu] = implode('-', array_slice($tuTen, $i, $dai));
                    }
                }
            }
        }

        return $kq;
    }

    /** Tiêu đề các tour đang bán (để bắt trùng), tính một lần mỗi lượt tải trang */
    private static function tieuDeTourKhac(): Collection
    {
        return once(fn () => Tour::query()->published()
            ->get(['id', 'name', 'seo_title', 'duration_days', 'duration_nights', 'adult_price'])
            ->map(fn (Tour $x) => ['id' => $x->getKey(), 'ten' => $x->name, 'tieu_de' => mb_strtolower(self::tieuDeGoc($x))]));
    }
}
