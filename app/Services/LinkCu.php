<?php

namespace App\Services;

use App\Models\Destination;
use App\Models\Redirect;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Guide\Models\Guide;
use Modules\Tour\Models\Tour;
use Modules\Visa\Models\VisaCountry;

/**
 * Link của website WordPress / WooCommerce cũ → trang tương ứng ở website mới.
 *
 * psvtravel.com.vn (bên khác giữ) chuyển sang psvtravel.com GIỮ NGUYÊN đường
 * dẫn, nên Google, khách cầm link cũ gõ vào psvtravel.com/tour_nuoc_ngoai/…,
 * /nhat-ban, /cua-hang… rồi gặp 404. Ở đây:
 *
 *   - Đã có trong bảng redirects (máy khớp từ trước hoặc nhân viên đặt) → dùng luôn.
 *   - Tour cũ: tìm tour đang bán trùng đường dẫn, không thì trùng nhiều cặp chữ
 *     hiếm ("cửu trại", "bangkok pattaya") nhất VÀ cùng nơi (Đài Loan không sang
 *     Nhật Bản). Không có tour tương tự thì về điểm đến / danh mục của đúng nước,
 *     vùng đó; không có trang riêng nào thì 410 — chuyển nhầm tệ hơn báo đã gỡ.
 *   - Bài theo chủ đề: visa → trang làm visa của nước đó, tin tuyển dụng →
 *     /tuyen-dung, vé máy bay, team building.
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
        '/quoc-gia' => '/tour-nuoc-ngoai',     // danh mục "Quốc gia" của web cũ
        '/tinh-thanh-pho' => '/tour-trong-nuoc', // danh mục "Tỉnh thành phố"
        '/chinh-sach-bao-mat-thong-tin' => '/chinh-sach-bao-mat',
        '/visa' => '/lam-visa',
        '/dich-vu-visa' => '/lam-visa',
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
        'chau-my', 'bac-au', 'tay-ban-nha', 'bo-dao-nha', 'ha-lan', 'luxembourg',
    ];

    private const TRONG_NUOC = [
        'da-nang', 'nha-trang', 'mui-ne', 'phan-thiet', 'da-lat', 'binh-ba', 'hue', 'hoi-an', 'phu-quoc', 'ha-long',
        'sa-pa', 'ha-giang', 'ha-noi', 'ninh-binh', 'quy-nhon', 'phu-yen', 'con-dao', 'vung-tau', 'can-tho',
        'mien-tay', 'mien-bac', 'mien-trung', 'mien-nam', 'tay-nguyen', 'quang-binh', 'moc-chau', 'cao-bang',
        'tay-ninh', 'chau-doc', 'ca-mau', 'binh-hung', 'nam-du', 'ly-son', 'ta-dung', 'binh-lieu',
        'my-tho', 'ben-tre', 'tien-giang', 'soc-trang', 'bac-lieu', 'hon-son', 'phu-quy', 'trang-an', 'bai-dinh',
        'tam-coc', 'yen-tu', 'lao-cai', 'fansipan', 'dong-van', 'bac-kan', 'phong-nha', 'ba-na', 'son-tra',
        'my-son', 'buon-ma-thuot', 'pleiku', 'kon-tum', 'ninh-chu', 'tay-bac', 'dong-bac',
    ];

    /** Thành phố, thắng cảnh nước ngoài hay có trong tên tour → nước */
    private const NOI_NUOC_NGOAI = [
        'truong-gia-gioi' => 'trung-quoc', 'phuong-hoang-co-tran' => 'trung-quoc', 'cuu-trai-cau' => 'trung-quoc',
        'thanh-do' => 'trung-quoc', 'bac-kinh' => 'trung-quoc', 'thuong-hai' => 'trung-quoc', 'hang-chau' => 'trung-quoc',
        'to-chau' => 'trung-quoc', 'quang-chau' => 'trung-quoc', 'le-giang' => 'trung-quoc', 'dai-ly' => 'trung-quoc',
        'con-minh' => 'trung-quoc', 'shangrila' => 'trung-quoc', 'thanh-dao' => 'trung-quoc', 'nam-ninh' => 'trung-quoc',
        'seoul' => 'han-quoc', 'busan' => 'han-quoc', 'jeju' => 'han-quoc', 'nami' => 'han-quoc', 'everland' => 'han-quoc',
        'tokyo' => 'nhat-ban', 'osaka' => 'nhat-ban', 'kyoto' => 'nhat-ban', 'nagoya' => 'nhat-ban', 'hokkaido' => 'nhat-ban',
        'phu-si' => 'nhat-ban', 'fukuoka' => 'nhat-ban', 'dai-bac' => 'dai-loan', 'cao-hung' => 'dai-loan',
        'dai-trung' => 'dai-loan', 'bangkok' => 'thai-lan', 'pattaya' => 'thai-lan', 'phuket' => 'thai-lan',
        'chiang-mai' => 'thai-lan', 'chiang-rai' => 'thai-lan', 'krabi' => 'thai-lan', 'phi-phi' => 'thai-lan',
        'jakarta' => 'indonesia', 'siem-reap' => 'campuchia', 'phnom-penh' => 'campuchia', 'mondulkiri' => 'campuchia',
        'sihanouk' => 'campuchia', 'bokor' => 'campuchia', 'koh-rong' => 'campuchia', 'vientiane' => 'lao',
        'luang-prabang' => 'lao', 'new-york' => 'my', 'washington' => 'my', 'los-angeles' => 'my', 'las-vegas' => 'my',
        'san-francisco' => 'my', 'sydney' => 'uc', 'melbourne' => 'uc', 'paris' => 'phap', 'vatican' => 'chau-au',
        'abu-dhabi' => 'dubai', 'istanbul' => 'tho-nhi-ky', 'cappadocia' => 'tho-nhi-ky',
    ];

    /** Cách viết khác của cùng một nơi → một cách */
    private const DONG_NGHIA = [
        'thuy-sy' => 'thuy-si', 'sapa' => 'sa-pa', 'sri-lanka' => 'srilanka', 'hongkong' => 'hong-kong',
        'australia' => 'uc', 'hoa-ky' => 'my', 'tq' => 'trung-quoc', 'shihanouk' => 'sihanouk', 'schengen' => 'chau-au',
        'macau' => 'ma-cao', 'taiwan' => 'dai-loan', 'japan' => 'nhat-ban', 'korea' => 'han-quoc', 'thailand' => 'thai-lan',
        'cambodia' => 'campuchia', 'shangri-la' => 'shangrila',
    ];

    /**
     * Vùng (thường là danh mục tour) → nước / nơi thuộc vùng. Tour cũ không còn
     * tour tương tự thì chuyển về danh mục của nước, không có thì của vùng.
     */
    private const VUNG = [
        'dong-nam-a' => ['thai-lan', 'singapore', 'malaysia', 'indonesia', 'bali', 'philippines', 'campuchia', 'lao', 'myanmar'],
        'dong-bac-a' => ['han-quoc', 'nhat-ban', 'dai-loan', 'hong-kong', 'ma-cao', 'trung-quoc', 'mong-co'],
        'chau-au' => ['phap', 'thuy-si', 'duc', 'anh', 'ha-lan', 'tay-ban-nha', 'bo-dao-nha', 'luxembourg', 'bac-au', 'nga'],
        'chau-my' => ['my', 'canada'],
        'chau-uc' => ['uc', 'new-zealand'],
        'trung-dong' => ['dubai', 'tho-nhi-ky', 'ai-cap'],
        'nam-a' => ['an-do', 'srilanka', 'nepal', 'bhutan', 'maldives'],
        'mien-tay' => ['my-tho', 'ben-tre', 'tien-giang', 'can-tho', 'soc-trang', 'bac-lieu', 'ca-mau', 'chau-doc',
            'hon-son', 'nam-du', 'binh-hung'],
        'mien-nam' => ['mien-tay', 'my-tho', 'ben-tre', 'tien-giang', 'can-tho', 'soc-trang', 'bac-lieu', 'ca-mau',
            'chau-doc', 'hon-son', 'nam-du', 'phu-quoc', 'con-dao', 'vung-tau', 'tay-ninh', 'mui-ne', 'phan-thiet',
            'binh-hung', 'binh-ba', 'phu-quy'],
        'mien-bac' => ['ha-noi', 'ha-long', 'ninh-binh', 'trang-an', 'bai-dinh', 'tam-coc', 'yen-tu', 'sa-pa', 'lao-cai',
            'fansipan', 'ha-giang', 'dong-van', 'cao-bang', 'bac-kan', 'moc-chau', 'binh-lieu', 'tay-bac', 'dong-bac'],
        'mien-trung' => ['da-nang', 'hoi-an', 'hue', 'quang-binh', 'phong-nha', 'ba-na', 'son-tra', 'my-son', 'quy-nhon',
            'phu-yen', 'ly-son', 'nha-trang', 'binh-ba', 'ninh-chu'],
        'tay-nguyen' => ['da-lat', 'ta-dung', 'buon-ma-thuot', 'pleiku', 'kon-tum'],
    ];

    /** Tên nơi ngắn dễ nhầm nghĩa khi nằm giữa đường dẫn ("anh", "duc"…): chỉ nhận khi đứng một mình */
    private const NOI_DE_NHAM = ['anh', 'duc', 'nga', 'y', 'bi'];

    /** Tệp máy móc tự dò (ứng dụng iOS, Android…): không phải trang, không lưu */
    private const KHONG_PHAI_TRANG = ['apple-app-site-association', 'assetlinks', 'humans', 'security'];

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
        [$kq, $luu] = self::khop($r->old_path);
        if (! $luu) {
            // Bộ khớp mới xếp vào loại không lưu (tệp máy móc dò…): bỏ dòng
            $r->delete();

            return;
        }
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
            if ($tour) {
                return [['ma' => 301, 'toi' => self::duongDanTour($tour)], true];
            }

            // Không còn tour tương tự: danh mục / điểm đến của đúng nước, vùng đó
            // (tour Nhật cũ → danh sách tour Nhật). Không có trang riêng thì 410
            // — danh sách chung chung Google cũng coi như trang đã gỡ.
            return [self::trangCuaNoi(self::noiChinh($doan[1]), true) ?? ['ma' => 410, 'toi' => null], true];
        }
        // Danh mục cũ nhiều cấp: /danh-muc-san-pham/nhat-ban, /product-category/chau-a/thai-lan
        if (in_array($doan[0], ['danh-muc-san-pham', 'product-category', 'danh-muc', 'category'], true) && count($doan) >= 2) {
            return [self::trangNoi(end($doan)) ?? ['ma' => 410, 'toi' => null], true];
        }
        if (count($doan) !== 1 || str_contains($p, '.')) {
            return [['ma' => 404, 'toi' => null], false];
        }

        $slug = $doan[0];
        if (in_array($slug, self::KHONG_PHAI_TRANG, true)) {
            return [['ma' => 404, 'toi' => null], false];
        }
        $kq = self::trangNoi($slug) ?? self::chuDe($slug) ?? self::baiGanNhat($slug)
            ?? (substr_count($slug, '-') >= 2 ? self::trangCuaNoi(self::noiChinh($slug), false) : null);
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

        // Trang của một nơi (/nhat-ban, /da-lat, /mien-tay): danh mục / điểm đến
        // của nơi đó hoặc vùng chứa nó, không có thì danh sách tour đúng loại.
        // Danh sách nơi viết sẵn đi trước dò tên tour: "my" (Mỹ) dò theo tên tour
        // sẽ dính "my-tho" (Mỹ Tho)
        $noi = self::DONG_NGHIA[$slug] ?? $slug;
        if (self::laNoi($noi)) {
            return self::trangCuaNoi([$noi], true)
                ?? ['ma' => 301, 'toi' => self::laNuocNgoai($noi) ? '/tour-nuoc-ngoai' : '/tour-trong-nuoc'];
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

        // Link cũ nói về nơi nào (Đài Loan, Trương Gia Giới…) thì mục được chọn
        // phải có ít nhất một nơi trong đó: "Cẩm nang Đài Loan từ A–Z" không được
        // sang "Cẩm nang Nhật Bản" chỉ vì cùng chữ "cẩm nang", "từ A–Z"
        $noiCu = self::noiChinh($slug);

        $tot = null;
        $diemTot = 0;
        foreach ($ds->values() as $i => $m) {
            $chung = array_intersect($tuCu, $tuMuc[$i]);
            if (count($chung) < $itNhat) {
                continue;
            }
            if ($noiCu && ! array_intersect($noiCu, self::noiTrong($m['chuoi'], false))) {
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

    /** Bài theo chủ đề: tin tuyển dụng, visa, vé máy bay, team building */
    private static function chuDe(string $slug): ?array
    {
        $s = "-{$slug}-";
        // Tin tuyển dụng cũ ("ke-toan-tong-hop-01-nhan-vien") — trước visa vì có
        // tin "truong-phong-visa-ho-chieu-02-nhan-vien"
        if (preg_match('/-nhan-vien$/', $slug) || str_starts_with($slug, 'tuyen-dung')) {
            return ['ma' => 301, 'toi' => '/tuyen-dung'];
        }
        if (str_contains($s, '-visa-') || str_contains($s, '-tham-than-nhan-') || str_contains($s, '-ho-chieu-')) {
            $nuoc = self::nuocVisa();
            foreach (self::noiTrong($slug, true) as $n) {
                // Nơi là vùng (Châu Âu, Châu Mỹ) mà không có trang visa riêng → nước đầu tiên của vùng có trang
                foreach ([$n, self::NOI_NUOC_NGOAI[$n] ?? null, ...(self::VUNG[$n] ?? [])] as $c) {
                    if ($c !== null && in_array($c, $nuoc, true)) {
                        return ['ma' => 301, 'toi' => "/lam-visa/{$c}"];
                    }
                }
            }

            return ['ma' => 301, 'toi' => '/lam-visa'];
        }
        if (str_contains($s, '-ve-may-bay-') || str_contains($s, '-ve-bay-')) {
            return ['ma' => 301, 'toi' => '/ve-may-bay'];
        }
        if (str_contains($slug, 'team') || str_contains($s, '-du-lich-cong-ty-') || str_contains($s, '-company-trip-')
            || str_contains($s, '-gala-dinner-') || str_contains($s, '-year-end-party-')) {
            return ['ma' => 301, 'toi' => '/team-building'];
        }

        return null;
    }

    /**
     * Các nơi nhắc trong đường dẫn, theo thứ tự xuất hiện, ghép dài trước
     * ("my-tho" trước "my"). $caTenNgan: nhận cả tên ngắn dễ nhầm ("anh", "duc").
     *
     * @return list<string>
     */
    private static function noiTrong(string $s, bool $caTenNgan = false): array
    {
        $tu = array_values(array_filter(explode('-', $s), fn ($w) => $w !== ''));
        $noi = [];
        for ($i = 0; $i < count($tu); $i++) {
            for ($dai = min(3, count($tu) - $i); $dai >= 1; $dai--) {
                $ten = implode('-', array_slice($tu, $i, $dai));
                $ten = self::DONG_NGHIA[$ten] ?? $ten;
                if (self::laNoi($ten)) {
                    // Nơi dùng để ví von: "Bali của Việt Nam", "Vịnh Hạ Long ở phương
                    // Nam", "Maldives thu nhỏ", "a little Paris of Vietnam" — bỏ
                    $viVon = in_array($tu[$i + $dai] ?? '', ['cua', 'o', 'thu', 'of'], true);
                    if (! $viVon && ($caTenNgan || ! in_array($ten, self::NOI_DE_NHAM, true))) {
                        $noi[] = $ten;
                    }
                    $i += $dai - 1;
                    break;
                }
            }
        }

        return array_values(array_unique($noi));
    }

    /**
     * Nơi chính của link cũ + các nơi cùng nước / cùng vùng với nó. Nơi chính là
     * nước ngoài đầu tiên (tour "Hà Nội – Seoul" là tour Hàn), không có thì nơi
     * đầu tiên. Bỏ nơi lạc vùng: "Hàn Quốc… từ Hà Nội" chỉ còn Hàn Quốc.
     *
     * @return list<string>
     */
    private static function noiChinh(string $slug): array
    {
        $noi = self::noiTrong($slug);
        if (! $noi) {
            return [];
        }
        $chinh = collect($noi)->first(fn ($n) => self::laNuocNgoai($n)) ?? $noi[0];
        $nhomChinh = self::nhom($chinh);

        return array_values(array_filter($noi, fn ($n) => (bool) array_intersect(self::nhom($n), $nhomChinh)));
    }

    /** Nơi + nước của nó + các vùng chứa nó */
    private static function nhom(string $n): array
    {
        $nuoc = self::NOI_NUOC_NGOAI[$n] ?? $n;
        $nhom = [$n, $nuoc];
        foreach (self::VUNG as $vung => $thanhVien) {
            if (in_array($vung, $nhom, true) || in_array($n, $thanhVien, true) || in_array($nuoc, $thanhVien, true)) {
                $nhom[] = $vung;
            }
        }

        return array_values(array_unique($nhom));
    }

    private static function laNoi(string $ten): bool
    {
        return in_array($ten, self::NUOC_NGOAI, true) || in_array($ten, self::TRONG_NUOC, true)
            || isset(self::NOI_NUOC_NGOAI[$ten]) || isset(self::VUNG[$ten]);
    }

    private static function laNuocNgoai(string $ten): bool
    {
        if (in_array($ten, self::NUOC_NGOAI, true) || isset(self::NOI_NUOC_NGOAI[$ten])) {
            return true;
        }
        foreach (['dong-nam-a', 'dong-bac-a', 'chau-au', 'chau-my', 'chau-uc', 'trung-dong', 'nam-a'] as $v) {
            if ($ten === $v || in_array($ten, self::VUNG[$v], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Trang riêng của các nơi: điểm đến, rồi danh mục tour (có tour đang bán)
     * của chính nơi đó / nước của nó; $theoVung thì thử thêm danh mục vùng.
     */
    private static function trangCuaNoi(array $noi, bool $theoVung): ?array
    {
        $diemDen = once(fn () => Destination::query()->dangHienThi()->pluck('slug')->all());
        $danhMuc = once(fn () => Category::query()->dangHienThi()
            ->whereHas('tours', fn ($q) => $q->published())->pluck('type', 'slug')->all());
        $toi = function (string $c) use ($diemDen, $danhMuc): ?array {
            if (in_array($c, $diemDen, true)) {
                return ['ma' => 301, 'toi' => "/diem-den/{$c}"];
            }
            if (isset($danhMuc[$c])) {
                return ['ma' => 301, 'toi' => ($danhMuc[$c] === 'domestic' ? '/tour-trong-nuoc' : '/tour-nuoc-ngoai')."?category={$c}"];
            }

            return null;
        };

        // Lượt 1: đúng nơi / nước. Lượt 2: vùng — để "Nhật Bản, Đài Loan" có
        // danh mục Đài Loan thì vào Đài Loan chứ không vào "Đông Bắc Á"
        foreach ($noi as $n) {
            foreach (array_unique([$n, self::NOI_NUOC_NGOAI[$n] ?? $n]) as $c) {
                if ($kq = $toi($c)) {
                    return $kq;
                }
            }
        }
        if ($theoVung) {
            foreach ($noi as $n) {
                $nuoc = self::NOI_NUOC_NGOAI[$n] ?? $n;
                foreach (self::VUNG as $vung => $thanhVien) {
                    if ((in_array($n, $thanhVien, true) || in_array($nuoc, $thanhVien, true)) && ($kq = $toi($vung))) {
                        return $kq;
                    }
                }
            }
        }

        return null;
    }

    /** @return list<string> đường dẫn các nước có trang làm visa */
    private static function nuocVisa(): array
    {
        return once(fn () => VisaCountry::query()->dangHienThi()->pluck('slug')->all());
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
