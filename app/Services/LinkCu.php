<?php

namespace App\Services;

use App\Models\Destination;
use App\Models\Redirect;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Guide\Models\Guide;
use Modules\Tour\Models\Tour;

/**
 * Link của website WordPress / WooCommerce cũ → trang tương ứng ở website mới.
 *
 * psvtravel.com.vn (bên khác giữ) chuyển sang psvtravel.com GIỮ NGUYÊN đường
 * dẫn, nên Google, khách cầm link cũ gõ vào psvtravel.com/tour_nuoc_ngoai/…,
 * /nhat-ban, /cua-hang… rồi gặp 404. Ở đây:
 *
 *   - Đã có trong bảng redirects (máy khớp từ trước hoặc nhân viên đặt) → dùng luôn.
 *   - Tour cũ: tìm tour đang bán trùng đường dẫn, không thì trùng nhiều cặp chữ
 *     hiếm ("cửu trại", "bangkok pattaya") nhất. Khớp lơ mơ thì KHÔNG chuyển
 *     (chuyển nhầm sang tour khác tệ hơn báo đã gỡ) → 410.
 *   - Trang một cấp (/nhat-ban, /da-lat, bài viết cũ): điểm đến / bài cẩm nang cùng
 *     đường dẫn, rồi danh sách tour theo nước / vùng, rồi bài cẩm nang gần giống.
 *   - Kết quả của link dạng web cũ được lưu lại để nhân viên xem / sửa trong admin
 *     (Hệ thống → Chuyển hướng link cũ). Link dò lung tung của bot (/.env…) thì
 *     không lưu, trả 404 như thường.
 *
 * @phpstan-type KetQua array{ma: int, toi: ?string}
 */
class LinkCu
{
    /** Tiền tố trang sản phẩm (tour) của web cũ */
    private const TIEN_TO_TOUR = ['tour_nuoc_ngoai', 'tour_trong_nuoc', 'tour', 'product', 'san-pham'];

    private const TRANG_CO_DINH = [
        '/tour_nuoc_ngoai' => '/tour-nuoc-ngoai',
        '/tour_trong_nuoc' => '/tour-trong-nuoc',
        '/tour' => '/tour-nuoc-ngoai',
        '/cua-hang' => '/tour-nuoc-ngoai', // trang "Cửa hàng" WooCommerce = mọi tour
        '/shop' => '/tour-nuoc-ngoai',
        '/ve-bay' => '/ve-may-bay',
        '/gioi-thieu' => '/ve-chung-toi',
        '/tin-tuc' => '/cam-nang',
        '/blog' => '/cam-nang',
        '/sitemap_index.xml' => '/sitemap.xml',
        '/sitemap.xml.gz' => '/sitemap.xml',
        '/wp-sitemap.xml' => '/sitemap.xml',
    ];

    /** Trang danh mục theo nước / vùng của web cũ (đường dẫn không dấu) */
    private const NUOC_NGOAI = [
        'australia', 'uc', 'nhat-ban', 'han-quoc', 'trung-quoc', 'dai-loan', 'hong-kong', 'ma-cao', 'thai-lan',
        'singapore', 'malaysia', 'indonesia', 'bali', 'philippines', 'campuchia', 'lao', 'myanmar', 'an-do',
        'srilanka', 'sri-lanka', 'tho-nhi-ky', 'dubai', 'chau-au', 'phap', 'thuy-si', 'anh', 'duc', 'my', 'hoa-ky',
        'canada', 'new-zealand', 'nga', 'ai-cap', 'maldives', 'nepal', 'bhutan', 'mong-co', 'chau-a', 'chau-uc',
    ];

    private const TRONG_NUOC = [
        'da-nang', 'nha-trang', 'mui-ne', 'phan-thiet', 'da-lat', 'binh-ba', 'hue', 'hoi-an', 'phu-quoc', 'ha-long',
        'sa-pa', 'sapa', 'ha-giang', 'ha-noi', 'ninh-binh', 'quy-nhon', 'phu-yen', 'con-dao', 'vung-tau', 'can-tho',
        'mien-tay', 'mien-bac', 'mien-trung', 'mien-nam', 'tay-nguyen', 'quang-binh', 'moc-chau', 'cao-bang',
        'tay-ninh', 'chau-doc', 'ca-mau', 'binh-hung', 'nam-du', 'ly-son', 'ta-dung', 'binh-lieu', 'trong-nuoc',
    ];

    /** Từ quá chung, không giúp phân biệt tour ("gia" giữ lại: Gia Lai, Trương Gia Giới) */
    private const BO_QUA = [
        'tour', 'du', 'lich', 'new', 'hot', 'va', 'cua', 'nhung', 'cac', 'tu', 'den', 'tai', 'trong', 'nuoc', 'ngoai',
        'kham', 'pha', 'tron', 'goi', 're', 'ngay', 'dem', 'hanh', 'trinh', 'mot', 'voi', 'cho', 'kdl',
    ];

    /** Tối đa số link "chưa khớp" máy tự lưu — chặn bot dò bừa làm phình bảng */
    public const TOI_DA_CHUA_KHOP = 2000;

    /**
     * Tra một link cũ, lưu kết quả vào bảng nếu là link dạng web cũ.
     *
     * @param  int  $luot  số lượt cộng vào cột "lượt" (0 = chỉ tra, không đếm)
     * @return KetQua
     */
    public static function tra(string $duongDan, int $luot = 1): array
    {
        $p = self::chuanHoa($duongDan);
        if ($p === null) {
            return ['ma' => 404, 'toi' => null];
        }

        $co = Redirect::query()->where('old_path', $p)->first();
        if ($co) {
            if ($luot > 0) {
                $co->increment('hits', $luot, ['last_hit_at' => now()]);
            }

            return self::ketQua($co);
        }

        [$kq, $luu] = self::khop($p);
        if ($luu && ($kq['ma'] !== 404 || Redirect::query()->where('status_code', 404)->count() < self::TOI_DA_CHUA_KHOP)) {
            try {
                Redirect::create(['old_path' => $p, 'new_path' => $kq['toi'], 'status_code' => $kq['ma'], 'source' => 'tu_dong'])
                    ->forceFill(['hits' => $luot, 'last_hit_at' => $luot > 0 ? now() : null])->save();
            } catch (QueryException) {
                // Hai lượt cùng lúc tạo trùng — bản kia đã lưu, bỏ qua
            }
        }

        return $kq;
    }

    /**
     * Như tra() nhưng không ghi gì vào bảng (lệnh psv:link-cu --xem)
     *
     * @return array{0: KetQua, 1: bool} kết quả + tra() có lưu dòng này không
     */
    public static function xem(string $duongDan): array
    {
        $p = self::chuanHoa($duongDan);
        if ($p === null) {
            return [['ma' => 404, 'toi' => null], false];
        }
        $co = Redirect::query()->where('old_path', $p)->first();

        return $co ? [self::ketQua($co), true] : self::khop($p);
    }

    /** @return KetQua */
    private static function ketQua(Redirect $r): array
    {
        return ['ma' => $r->status_code, 'toi' => $r->status_code === 301 ? $r->new_path : null];
    }

    /** Khớp lại một dòng do máy tạo (nút "Tự khớp lại" trong admin) */
    public static function khopLai(Redirect $r): void
    {
        if ($r->source !== 'tu_dong') {
            return;
        }
        [$kq] = self::khop($r->old_path);
        $r->forceFill(['new_path' => $kq['toi'], 'status_code' => $kq['ma']])->save();
    }

    /** "/Tour_Nuoc_Ngoai/abc/?x=1" → "/tour_nuoc_ngoai/abc"; null nếu không phải đường dẫn hợp lệ */
    public static function chuanHoa(string $duongDan): ?string
    {
        $p = strtolower(rawurldecode((string) parse_url($duongDan, PHP_URL_PATH)));
        $p = '/'.trim(preg_replace('#/+#', '/', $p), '/');
        if ($p === '/' || strlen($p) > 255 || ! preg_match('#^/[a-z0-9_./-]+$#', $p) || str_contains($p, '..')) {
            return null;
        }

        return $p;
    }

    /**
     * @return array{0: KetQua, 1: bool} kết quả + có lưu vào bảng không
     */
    private static function khop(string $p): array
    {
        $doan = explode('/', ltrim($p, '/'));

        if (isset(self::TRANG_CO_DINH[$p])) {
            return [['ma' => 301, 'toi' => self::TRANG_CO_DINH[$p]], true];
        }
        // Tệp / trang hệ thống WordPress: đã gỡ hẳn, không lưu (rất nhiều ảnh)
        if (in_array($doan[0], ['wp-content', 'wp-includes', 'wp-admin', 'wp-json', 'xmlrpc.php', 'wp-login.php', 'feed'], true)
            || end($doan) === 'feed' || preg_match('#-sitemap\d*\.xml$#', $p)) {
            return [str_ends_with($p, '.xml') ? ['ma' => 301, 'toi' => '/sitemap.xml'] : ['ma' => 410, 'toi' => null], false];
        }

        // Tour cũ: /tour_nuoc_ngoai/<slug>, /tour/<slug>…
        if (count($doan) === 2 && in_array($doan[0], self::TIEN_TO_TOUR, true)) {
            $tour = self::tourGanNhat($doan[1]);

            return [$tour ? ['ma' => 301, 'toi' => self::duongDanTour($tour)] : ['ma' => 410, 'toi' => null], true];
        }
        // Danh mục cũ nhiều cấp: /danh-muc-san-pham/nhat-ban, /product-category/chau-a/thai-lan
        if (in_array($doan[0], ['danh-muc-san-pham', 'product-category', 'danh-muc', 'category'], true) && count($doan) >= 2) {
            return [self::trangNoi(end($doan)) ?? ['ma' => 410, 'toi' => null], true];
        }
        if (count($doan) !== 1 || str_contains($p, '.')) {
            return [['ma' => 404, 'toi' => null], false];
        }

        $slug = $doan[0];
        $kq = self::trangNoi($slug) ?? self::baiGanNhat($slug);
        if ($kq) {
            return [$kq, true];
        }

        // Giống đường dẫn bài viết cũ (3 từ trở lên) mà chưa khớp được → lưu "chờ xử lý"
        // để nhân viên chọn trang thay thế; còn lại (dò bừa của bot) không lưu
        return [['ma' => 404, 'toi' => null], substr_count($slug, '-') >= 2];
    }

    /** Trang một cấp: điểm đến, bài cẩm nang cùng đường dẫn, danh sách tour theo nơi */
    private static function trangNoi(string $slug): ?array
    {
        if (Destination::query()->dangHienThi()->where('slug', $slug)->exists()) {
            return ['ma' => 301, 'toi' => "/diem-den/{$slug}"];
        }
        if (Guide::query()->dangHienThi()->where('slug', $slug)->exists()) {
            return ['ma' => 301, 'toi' => "/cam-nang/{$slug}"];
        }

        // Danh sách nơi viết sẵn trước: tên ngắn như "my" (Mỹ) mà dò theo tên tour
        // sẽ dính "my-tho" (Mỹ Tho)
        if (in_array($slug, self::NUOC_NGOAI, true)) {
            return ['ma' => 301, 'toi' => '/tour-nuoc-ngoai'];
        }
        if (in_array($slug, self::TRONG_NUOC, true)) {
            return ['ma' => 301, 'toi' => '/tour-trong-nuoc'];
        }

        // Nơi khác: tour đang bán có tên nơi này → danh sách đúng loại
        $loai = self::tours()
            ->filter(fn ($t) => str_contains('-'.$t['chuoi'].'-', "-{$slug}-"))
            ->countBy('type');
        if ($loai->isNotEmpty()) {
            return ['ma' => 301, 'toi' => $loai->sortDesc()->keys()->first() === 'domestic' ? '/tour-trong-nuoc' : '/tour-nuoc-ngoai'];
        }

        return null;
    }

    private static function tourGanNhat(string $slug): ?array
    {
        $ds = self::tours();

        return $ds->firstWhere('slug', $slug) ?? self::ganNhat($slug, $ds, 0.6, 2);
    }

    private static function baiGanNhat(string $slug): ?array
    {
        if (substr_count($slug, '-') < 2) {
            return null;
        }
        $bai = self::ganNhat($slug, Guide::query()->dangHienThi()->get(['slug', 'title'])
            ->map(fn ($g) => ['slug' => $g->slug, 'chuoi' => $g->slug.'-'.Str::slug((string) $g->title)]), 0.7, 2);

        return $bai ? ['ma' => 301, 'toi' => "/cam-nang/{$bai['slug']}"] : null;
    }

    /**
     * Mục trùng nhiều nhất theo CẶP CHỮ LIỀN NHAU ("cuu trai", "my tho", "phu
     * quoc"), có trọng số: cặp càng ít mục có càng nặng ký. So cặp chứ không so
     * chữ lẻ vì địa danh tiếng Việt gồm nhiều tiếng — "Trương Gia Giới" và "Thế
     * Giới Cổ Tích" chung chữ "giới" nhưng không chung cặp nào. Cặp không phân
     * biệt thứ tự (Bangkok - Pattaya = Pattaya - Bangkok).
     *
     * Chỉ tính cặp có trong danh sách hiện tại (bỏ chữ quảng cáo kiểu "song sánh
     * đảo ngọc"); phải đạt tỉ lệ $nguong và trùng ít nhất $itNhat cặp.
     */
    private static function ganNhat(string $slug, Collection $ds, float $nguong, int $itNhat): ?array
    {
        if ($ds->isEmpty()) {
            return null;
        }

        $tuMuc = $ds->map(fn ($m) => self::capChu($m['chuoi']));
        $soMuc = $tuMuc->count();
        $df = $tuMuc->flatten()->countBy();
        $nang = fn (string $w) => log(1 + $soMuc / $df[$w]);

        $tuCu = array_values(array_filter(self::capChu($slug), fn ($w) => isset($df[$w])));
        $tong = array_sum(array_map($nang, $tuCu));
        if (count($tuCu) < $itNhat || $tong <= 0) {
            return null;
        }

        $tot = null;
        $diemTot = 0;
        foreach ($ds->values() as $i => $m) {
            $chung = array_intersect($tuCu, $tuMuc[$i]);
            if (count($chung) < $itNhat) {
                continue;
            }
            $diem = array_sum(array_map($nang, $chung)) / $tong;
            if ($diem > $diemTot) {
                [$tot, $diemTot] = [$m, $diem];
            }
        }

        return $diemTot >= $nguong ? $tot : null;
    }

    /** "the-gioi-co-tich" → ["gioi|the", "co|gioi", "co|tich"] (bỏ từ chung, số trơn) */
    private static function capChu(string $s): array
    {
        $tu = array_values(array_filter(explode('-', $s),
            fn ($w) => $w !== '' && ! in_array($w, self::BO_QUA, true) && ! ctype_digit($w)));
        $cap = [];
        for ($i = 1; $i < count($tu); $i++) {
            if ($tu[$i] !== $tu[$i - 1]) {
                $cap[] = min($tu[$i], $tu[$i - 1]).'|'.max($tu[$i], $tu[$i - 1]);
            }
        }

        return array_values(array_unique($cap));
    }

    /** Tour đang hiển thị: slug, loại, chuỗi từ khoá (slug + tên không dấu + "5n4d") */
    private static function tours(): Collection
    {
        return once(fn () => Tour::query()->published()->orderBy('sort_order')->orderBy('id')
            ->get(['slug', 'name', 'type', 'duration_days', 'duration_nights'])
            ->map(fn (Tour $t) => [
                'slug' => $t->slug,
                'type' => $t->type,
                'chuoi' => $t->slug.'-'.Str::slug((string) $t->name)
                    .($t->duration_days ? "-{$t->duration_days}n".((int) $t->duration_nights).'d' : ''),
            ]));
    }

    private static function duongDanTour(array $t): string
    {
        return ($t['type'] === 'abroad' ? '/tour-nuoc-ngoai/' : '/tour-trong-nuoc/').$t['slug'];
    }
}
