<?php

namespace Modules\Maldives\Services\NhapCombo;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Modules\Tour\Services\NhapTour\PhanTichChuongTrinh;

/**
 * Đọc dãy "mục" của file chương trình combo Maldives (DocWord / DocPdf +
 * BoCucPdf) thành dữ liệu combo + resort + hạng phòng + bảng giá.
 *
 * Bố cục file đối tác (Sun Siyam World, Olhuveli, Prestige Vadoo,
 * Meedhupparu, Heritance Aarah, Hudhuranfushi…):
 *  - trang bìa: TÊN RESORT 5*, Thời gian, Hàng không, Khởi hành;
 *  - giới thiệu resort, bảng "Lịch bay từ Hà Nội | Hồ Chí Minh | Đà Nẵng";
 *  - LỊCH TRÌNH (dòng tóm tắt lưu trú) + NGÀY 01: … (bữa ăn) — xen giữa có các
 *    khung tiện ích resort (nhà hàng, giải trí, thể thao…);
 *  - GIÁ COMBO (USD/khách): hạng phòng × ngày khởi hành, "NA" = không bán,
 *    "Khách khởi hành từ TP HCM phụ thu $100 - $120";
 *  - Note / LƯU Ý ĐẶC BIỆT, DỊCH VỤ ĐÃ BAO GỒM, KHÔNG BAO GỒM, QUY ĐỊNH CỦA RESORT.
 *
 * Phần nào không chắc thì ghi vào "canh_bao" để nhân viên xem lại.
 */
class PhanTichComboMaldives
{
    /** Gạch đầu dòng ở đầu dòng chữ */
    private const GACH = '/^\s*(?:[•●▪■◆◦‣∙·✓✔➢➤►»\p{So}*]+|[\-–+](?=\s|\p{L}))\s*/u';

    private const NGAY = '/^NG[ÀA]Y\s*0?(\d{1,2})\s*[:.\-–]\s*(.*)$/iu';

    /** Từ khoá tiêu đề khung tiện ích resort (so chữ không dấu) */
    private const TIEN_ICH = '/NHA HANG|\bGOI\b|MIEN PHI|GIAI TRI|SUC KHOE|THE THAO|TRE EM|PHUC VU|HOAT DONG|\bTOUR\b|WATER SPORT|\bBAR\b|TIEN ICH|\bSPA\b|CHI TIET/';

    /** Mã hãng bay → tên (khi trang bìa không ghi hãng, lấy theo tên file "…Bay_SQ") */
    private const HANG_BAY = ['SQ' => 'Singapore Airlines', 'MH' => 'Malaysia Airlines', 'VN' => 'Vietnam Airlines', 'TG' => 'Thai Airways',
        'EK' => 'Emirates', 'QR' => 'Qatar Airways', 'AK' => 'AirAsia', 'OD' => 'Batik Air', 'UL' => 'SriLankan Airlines'];

    /** Từ tiếng Việt không dấu hay gặp — không viết hoa khi đổi tiêu đề VIẾT HOA về chữ thường */
    private const TU_VIET = ['bay', 'ra', 'vao', 'xe', 'tham', 'quan', 'tau', 'toi', 'con', 'nha', 'hang', 'an', 'tu', 'do', 'la', 'di', 'sang', 'nghi',
        'tam', 'ban', 'tai', 'sau', 'mua', 'choi', 'cung', 'theo', 'gia', 'phi', 'goi', 'dua', 'don', 'tra', 'phong', 'cao', 'toc', 'thuy', 'co', 'may',
        'hon', 'nam', 'bac', 'tay', 'trong', 'ngoai', 'giua', 'hai', 'ba', 'bon', 'voi', 'cho', 'khi', 'thi', 'nhung', 'cac', 'mot', 'it', 'lon', 'nho',
        'moi', 'cu', 'bo', 'me', 'em', 'anh', 'chi', 'ong', 'ai', 'hay', 'va', 'dao', 'lan', 'xem', 'ngam', 'hoang', 'tro', 've', 'thu', 'den', 'thao', 'tin', 'gian', 'chung', 'dung', 'trang', 'tang', 'xanh', 'canh', 'quang', 'thanh', 'nhan', 'phan', 'dan', 'lang', 'song', 'ngan', 'resort'];

    /** @var list<array> */
    private array $ds = [];

    /** @var list<string> */
    private array $canhBao = [];

    /** @var list<string> tên riêng giữ nguyên chữ hoa khi đổi chữ VIẾT HOA về chữ thường */
    private array $tenRieng = [];

    /**
     * @param  list<array>  $muc
     * @return array<string, mixed>
     */
    public function phanTich(array $muc, string $tenFile = ''): array
    {
        $this->canhBao = [];
        $this->ds = $this->chuanHoa($muc);
        $toan = implode("\n", array_map(fn ($m) => $this->chuCua($m), array_filter($this->ds, fn ($m) => $m['loai'] !== 'nhom_tien_ich')));

        // ---- Tên resort, hạng sao ----
        $resort = $this->resort($tenFile);
        $this->tenRieng = array_values(array_filter(array_merge(
            preg_split('/\s+/u', $resort['ten']),
            ['Việt Nam', 'Male', 'Malé', 'Maldives', 'Singapore', 'Kuala Lumpur', 'Velana', 'Hulhumale', 'WOW', 'All Inclusive', 'Premium', 'Full Board',
                'Half Board', 'TP.HCM', 'HCM', 'Hà Nội', 'Đà Nẵng', 'Atoll', 'Villa', 'Spa', 'Bar', 'Buffet', 'Set Menu', 'Gala Dinner'],
        )));

        // ---- Lịch bay theo nơi khởi hành (bảng "Lịch bay từ …") ----
        [$noiDi, $daDung] = $this->lichBay();

        // ---- Các mốc: LỊCH TRÌNH, các ngày, cuối lịch trình ----
        $ngay = $this->cacNgay();
        $iLichTrinh = null;
        foreach ($this->ds as $i => $m) {
            if ($m['loai'] === 'chu' && preg_match('/^LICH TRINH\b/', self::khoa($m['chu']))) {
                $iLichTrinh = $i;
                break;
            }
        }
        $dauNgay = $ngay ? $ngay[0]['i'] : count($this->ds);
        $cuoiLichTrinh = count($this->ds);
        if ($ngay) {
            for ($i = $ngay[count($ngay) - 1]['i'] + 1; $i < count($this->ds); $i++) {
                $td = $this->tieuDePhan($this->ds[$i]);
                if ($td && $td['loai'] !== 'khong_bao_gom') {
                    $cuoiLichTrinh = $i;
                    break;
                }
            }
        }
        $hetBia = min($iLichTrinh ?? $dauNgay, $dauNgay);
        foreach ($this->ds as $i => $m) {
            if ($i < $hetBia && in_array($this->tieuDePhan($m)['loai'] ?? null, ['gia', 'bao_gom', 'khong_bao_gom', 'quy_dinh'], true)) {
                $hetBia = $i; // bảng giá / bao gồm đặt trước lịch trình
                break;
            }
        }

        // ---- Trang bìa + giới thiệu ----
        $bia = [];
        for ($i = 0; $i < $hetBia; $i++) {
            if (! isset($daDung[$i])) {
                $bia[$i] = $this->ds[$i];
            }
        }
        $chuBia = implode("\n", array_map(fn ($m) => $this->chuCua($m), $bia));
        [$soNgay, $soDem] = preg_match('/(\d{1,2})\s*ngày\s*[–\-]?\s*(\d{1,2})\s*đêm/iu', $chuBia, $k) ? [(int) $k[1], (int) $k[2]] : [null, null];
        $hangBay = $this->hangBay($chuBia, $tenFile);
        [$gioiThieu, $noiBatResort, $chiNguoiLon] = $this->gioiThieu($bia);

        // ---- Lịch trình từng ngày ----
        $tienIch = [];
        $khongBaoGom = [];
        $lichTrinh = [];
        $anh = [];
        foreach ($ngay as $k => $n) {
            $het = isset($ngay[$k + 1]) ? $ngay[$k + 1]['i'] : $cuoiLichTrinh;
            [$dong, $ti, $kbg, $anhNgay] = $this->noiDungNgay($n['i'] + 1, $het);
            array_push($tienIch, ...$ti);
            array_push($khongBaoGom, ...$kbg);
            foreach ($anhNgay as $a) {
                $anh[] = $a + ['ngay' => empty($a['cua_resort']) ? $n['so'] : null];
            }
            $lichTrinh[] = ['ngay' => $n['so'], 'tieu_de' => $this->cau($n['tieu_de']), 'bua' => $n['bua'], 'noi_dung' => implode("\n", $dong)];
        }
        if (! $ngay) {
            $this->canhBao[] = 'Không tìm thấy lịch trình từng ngày (các dòng "NGÀY 01: …") — nhập tay phần lịch trình.';
        }

        // ---- Các phần ngoài lịch trình: bảng giá, bao gồm, lưu ý, quy định ----
        $phan = $this->cacPhan($hetBia, $dauNgay, $cuoiLichTrinh, $daDung);
        array_push($tienIch, ...$phan['tien_ich']);
        $khongBaoGom = array_merge($phan['khong_bao_gom'], $khongBaoGom);
        $chiNguoiLon = $chiNguoiLon || $phan['chi_nguoi_lon'];
        foreach ($this->ds as $i => $m) {
            if ($m['loai'] === 'anh' && ($i < $dauNgay || $i >= $cuoiLichTrinh)) {
                $anh[] = $m + ['ngay' => null];
            }
        }

        // ---- Bảng giá → hạng phòng + ngày khởi hành ----
        [$phong, $cacNgay] = $this->bangGia($phan['bang'], $chiNguoiLon);

        // ---- Nơi khởi hành + phụ thu ----
        $noiDi = $this->noiKhoiHanh($noiDi, $toan);

        // ---- Lưu trú, gói dịch vụ, di chuyển ----
        $luuTru = $this->luuTru($iLichTrinh, $dauNgay);
        $goi = $this->goiDichVu($luuTru.' '.$toan);
        $vanChuyen = $this->vanChuyen($chuBia, $phan['bao_gom'], $toan);
        $dao = $this->dao($toan, $chuBia);

        $sao = $resort['sao'] !== null ? str_replace('.', ',', (string) $resort['sao']) : null;
        $ten = trim($resort['ten'].($sao ? " {$sao}*" : '').($resort['hang'] ? ' '.$resort['hang'] : '')
            .($hangBay ? " – bay {$hangBay}" : '').($vanChuyen ? ', '.mb_strtolower($vanChuyen) : ''));

        if ($lichTrinh && $soNgay && count($lichTrinh) !== $soNgay) {
            $this->canhBao[] = 'Lịch trình có '.count($lichTrinh)." ngày nhưng thời gian combo ghi {$soNgay} ngày — kiểm tra lại.";
        }
        if (! $hangBay) {
            $this->canhBao[] = 'Không thấy hãng bay — điền ô "Hãng bay".';
        }

        $treEm = preg_match('/(\d{2,3})\s*%\s*(?:giá\s*)?(?:của\s*)?người lớn/iu', $toan, $k) ? (int) $k[1] : null;
        if ($treEm === null && $cacNgay && ! $chiNguoiLon) {
            $this->canhBao[] = 'Không thấy giá trẻ em (% giá người lớn) — điền ô "Trẻ em tính (% giá người lớn)".';
        }

        return [
            'resort' => [
                'ten' => $resort['ten'],
                'sao' => $resort['sao'],
                'dao' => $dao,
                'goi' => $goi,
                'gioi_thieu' => $gioiThieu,
                'noi_bat' => array_slice($noiBatResort, 0, 8),
                'tien_ich' => array_values(array_map(fn ($n) => ['title' => $this->cau($n['title']), 'items' => $n['items']], array_filter($tienIch, fn ($n) => $n['items']))),
                'quy_dinh' => $phan['quy_dinh'],
                'chi_nguoi_lon' => $chiNguoiLon,
            ],
            'phong' => $phong,
            'ten' => $ten,
            'tom_tat' => $this->tomTat($resort, $sao, $dao, $soNgay, $soDem, $luuTru, $hangBay, $vanChuyen),
            'so_ngay' => $soNgay ?: (count($lichTrinh) ?: null),
            'so_dem' => $soDem,
            'hang_bay' => $hangBay,
            'van_chuyen' => $vanChuyen,
            'luu_tru' => $luuTru,
            'noi_khoi_hanh' => $noiDi,
            'ngay' => $cacNgay,
            'tre_em_phan_tram' => $treEm,
            'tuoi_tre_em' => preg_match('/trẻ em\s*từ\s*0?(\d{1,2})\s*[–\-]\s*0?(\d{1,2})\s*tuổi/iu', $toan, $k)
                || preg_match('/trẻ em\s*0?(\d{1,2})\s*[–\-]\s*0?(\d{1,2})\s*tuổi/iu', $toan, $k) ? "{$k[1]} – {$k[2]} tuổi" : null,
            'toi_thieu' => preg_match('/không đủ\s+(\d{1,3})\s+khách/iu', $toan, $k) ? (int) $k[1] : null,
            'lich_trinh' => $lichTrinh,
            'bao_gom' => $phan['bao_gom'],
            'khong_bao_gom' => array_values(array_unique($khongBaoGom)),
            'luu_y' => $phan['luu_y'],
            'anh' => $this->locAnh($anh),
            'canh_bao' => array_values(array_unique($this->canhBao)),
        ];
    }

    // ===================================================================
    // Chuẩn hoá
    // ===================================================================

    /** @return list<array> */
    private function chuanHoa(array $muc): array
    {
        $ra = [];
        foreach ($muc as $m) {
            if ($m['loai'] !== 'chu') {
                $ra[] = $m;

                continue;
            }
            $o = ! empty($m['o']) ? array_map(fn ($x) => trim(preg_replace('/[ \t]*\n[ \t]*/u', "\n", PhanTichChuongTrinh::chuan($x))), $m['o']) : null;
            $gach = (bool) ($m['gach'] ?? false);
            // PDF: gạch đầu dòng cách xa chữ thành một "ô" riêng ("- | Vé máy bay khứ hồi…")
            if ($o && count($o) >= 2 && preg_match('/^[-–•●▪+✓✔*]$/u', $o[0])) {
                array_shift($o);
                $gach = true;
                $o = count($o) > 1 ? array_values($o) : null;
                $m['chu'] = $o ? '' : (string) array_values(array_slice($m['o'], 1))[0];
            }
            $chu = $o ? implode(' | ', $o) : PhanTichChuongTrinh::chuan($m['chu']);
            if (! $o && preg_match(self::GACH, $chu)) {
                $chu = trim(preg_replace(self::GACH, '', $chu));
                $gach = true;
            }
            // số trang, dòng trang trí "*****"
            if ($chu === '' || preg_match('/^\d{1,2}$|^[*\s|]+$/u', $chu)) {
                continue;
            }
            $ra[] = ['loai' => 'chu', 'chu' => $chu, 'dam' => (bool) ($m['dam'] ?? false), 'gach' => $gach, 'o' => $o];
        }

        // Bảng Word "TIÊU ĐỀ 1 | TIÊU ĐỀ 2 | …" + hàng nội dung cùng số ô = khung tiện ích nhiều cột
        $gop = [];
        for ($i = 0; $i < count($ra); $i++) {
            $m = $ra[$i];
            $sau = $ra[$i + 1] ?? null;
            if ($m['loai'] === 'chu' && $m['o'] && count($m['o']) >= 2 && $sau && $sau['loai'] === 'chu' && $sau['o'] && count($sau['o']) === count($m['o'])
                && ! array_filter($m['o'], fn ($x) => ! self::laTieuDeO($x))) {
                $nhom = [];
                foreach ($m['o'] as $c => $tieuDe) {
                    $muc = array_values(array_filter(array_map(fn ($x) => trim(preg_replace(self::GACH, '', $x)), explode("\n", $sau['o'][$c]))));
                    $nhom[] = ['title' => $tieuDe, 'items' => $muc];
                }
                $gop[] = ['loai' => 'nhom_tien_ich', 'nhom' => $nhom];
                $i++;

                continue;
            }
            $gop[] = $m;
        }

        return $gop;
    }

    /** Ô tiêu đề cột của khung tiện ích: chữ viết hoa ngắn, không phải chuyến bay / ngày / giá */
    private static function laTieuDeO(string $x): bool
    {
        $chu = preg_match_all('/\p{L}/u', $x);

        return self::hoa($x) && mb_strlen($x) <= 60 && $chu >= 3 && $chu >= 0.5 * mb_strlen(preg_replace('/\s/u', '', $x))
            && ! preg_match('/^NGAY\s*\d|LOAI PHONG|LICH BAY|GIA COMBO|\b[A-Z0-9]{2}\s?\d{2,4}\s+[A-Z]{6}\b/', self::khoa($x));
    }

    private function chuCua(array $m): string
    {
        return match ($m['loai']) {
            'chu' => $m['o'] ? implode(' ', array_filter($m['o'], fn ($x) => $x !== '')) : $m['chu'],
            'nhom_tien_ich' => implode("\n", array_map(fn ($n) => $n['title'].': '.implode('; ', $n['items']), $m['nhom'])),
            'bang_gia' => implode(' ', $m['cot']),
            default => '',
        };
    }

    /** Bỏ dấu, viết hoa — để so từ khoá */
    private static function khoa(string $s): string
    {
        return trim(strtoupper(Str::ascii($s)));
    }

    /** Chữ viết hoa (≥ 70% chữ cái là chữ hoa) */
    private static function hoa(string $s): bool
    {
        $chu = preg_replace('/[^\p{L}]/u', '', $s);

        return $chu !== '' && mb_strlen(preg_replace('/[^\p{Lu}]/u', '', $chu)) >= mb_strlen($chu) * 0.7;
    }

    /**
     * Chữ VIẾT HOA → chữ thường, viết hoa chữ đầu và giữ chữ hoa cho tên riêng
     * (tên resort, Male, Maldives…) và từ không dấu không phải tiếng Việt
     * ("SÂN BAY VELANA – MALE" → "Sân bay Velana – Male"). Chữ đã viết thường
     * sẵn thì giữ nguyên.
     */
    private function cau(string $s): string
    {
        $s = trim($s);
        // "TOUR/ DỊCH VỤ MIỄN PHÍ (khách đặt trực tiếp…)": chỉ đổi phần viết hoa trước ngoặc
        if (! self::hoa($s) && preg_match('/^([^(]+?)\s*(\(.*)$/u', $s, $k) && self::hoa($k[1])) {
            return $this->cau($k[1]).' '.$k[2];
        }
        if (! self::hoa($s)) {
            return $s;
        }
        $s = preg_replace('/\bKS\b/u', 'khách sạn', $s);
        $thuong = mb_strtolower($s);
        $thuong = preg_replace_callback('/(?<![\p{L}\d])([a-z]{2,})(?![\p{L}])/u', fn ($k) => in_array($k[1], self::TU_VIET, true) ? $k[1] : ucfirst($k[1]), $thuong);
        foreach ($this->tenRieng as $ten) {
            $thuong = preg_replace('/(?<![\p{L}])'.preg_quote(mb_strtolower($ten), '/').'(?![\p{L}])/iu', $ten, $thuong);
        }
        $thuong = preg_replace('/\bviet nam\b/iu', 'Việt Nam', $thuong);

        return mb_strtoupper(mb_substr($thuong, 0, 1)).mb_substr($thuong, 1);
    }

    /** Câu viết thường, viết hoa chữ đầu ("Bữa sáng, bữa trưa, Đồ uống" → "Bữa sáng, bữa trưa, đồ uống") */
    private static function thuong(string $s): string
    {
        $s = mb_strtolower(trim($s));

        return mb_strtoupper(mb_substr($s, 0, 1)).mb_substr($s, 1);
    }

    // ===================================================================
    // Trang bìa
    // ===================================================================

    /** @return array{ten: string, sao: ?float, hang: ?string} */
    private function resort(string $tenFile): array
    {
        foreach (array_slice($this->ds, 0, 12) as $m) {
            if ($m['loai'] !== 'chu' || $m['o'] || ! self::hoa($m['chu'])
                || ! preg_match('/^(.+?)\s*(\d(?:[.,]5)?)\s*\*\s*(?:[-–]\s*(.+))?$/u', $m['chu'], $k)) {
                continue;
            }
            $ten = trim(preg_replace('/^RESORT\s+|\s+RESORT$/u', '', trim($k[1])));
            if ($ten === '') {
                continue;
            }

            return [
                'ten' => mb_convert_case(mb_strtolower($ten), MB_CASE_TITLE),
                'sao' => (float) str_replace(',', '.', $k[2]),
                'hang' => isset($k[3]) && $k[3] !== '' ? mb_convert_case(mb_strtolower(trim($k[3])), MB_CASE_TITLE) : null,
            ];
        }

        // Theo tên file: "SIYAM_WORLD_5__-_Bay_SQ.pdf", "OLHUVELI_4.5__-_Bay_SQ.pdf"
        $goc = preg_replace('/[_\s]*-[_\s]*bay[_\s].*$/i', '', pathinfo($tenFile, PATHINFO_FILENAME));
        $sao = preg_match('/(\d(?:[.,]5)?)_*$/', $goc, $k) ? (float) str_replace(',', '.', $k[1]) : null;
        $ten = trim(str_replace('_', ' ', preg_replace('/[_\s]*\d(?:[.,]5)?_*$/', '', $goc)));
        $this->canhBao[] = 'Không thấy tên resort trên trang bìa — lấy theo tên file, kiểm tra lại.';

        return ['ten' => mb_convert_case(mb_strtolower($ten ?: 'Resort Maldives'), MB_CASE_TITLE), 'sao' => $sao, 'hang' => null];
    }

    private function hangBay(string $bia, string $tenFile): ?string
    {
        if (preg_match('/Hàng không\s*:\s*(.+?)(?=\s+(?:(?:Thời gian|Khởi hành|Vị trí|Vận chuyển|Lưu trú)\s*:|Gói\s)|\n|$)/iu', $bia, $k)) {
            $hang = preg_replace(['/\s+[–\-]\s+.*$/u', '/\s*\d(?:[.,]5)?\s*(?:\*|sao)\s*$/iu', '/\s*\/\s*/u'], ['', '', ' / '], trim($k[1]));
            if ($hang !== '') {
                return $hang;
            }
        }
        if (preg_match('/bay[_\s-]+([A-Z0-9]{2})\b/i', $tenFile, $k) && isset(self::HANG_BAY[strtoupper($k[1])])) {
            return self::HANG_BAY[strtoupper($k[1])];
        }

        return null;
    }

    /**
     * Phần giới thiệu resort trước lịch trình: đoạn văn → giới thiệu, gạch đầu
     * dòng → điểm nổi bật. Bỏ các dòng thông tin trang bìa và tiêu đề.
     *
     * @param  array<int, array>  $bia
     * @return array{0: ?string, 1: list<string>, 2: bool} giới thiệu, nổi bật, resort chỉ nhận người lớn
     */
    private function gioiThieu(array $bia): array
    {
        $doan = [];
        $noiBat = [];
        $chiNguoiLon = false;
        foreach ($bia as $m) {
            if ($m['loai'] !== 'chu' || $m['o']) {
                continue;
            }
            $chu = $m['chu'];
            $khoa = self::khoa($chu);
            if (preg_match('/DANH CHO NGUOI LON|TU 18 TUOI|ADULTS?[\s-]*ONLY/', $khoa) && self::hoa($chu)) {
                $chiNguoiLon = true;

                continue;
            }
            if ($m['gach'] && ! self::hoa($chu)) {
                $noiBat[] = Str::limit(rtrim($chu, ' .'), 300, '');

                continue;
            }
            if (preg_match('/^(THOI GIAN|HANG KHONG|KHOI HANH|VI TRI|VAN CHUYEN|GOI\b|LUU TRU)/', $khoa) || self::hoa($chu)) {
                continue;
            }
            if (mb_strlen($chu) < 40 && ! preg_match('/[.!?]$/u', $chu)) {
                continue; // tiêu đề nhỏ "Điểm nổi bật tại Meedhupparu"
            }
            // PDF ngắt đoạn khi đổi kiểu chữ giữa câu → nối nếu đoạn trước chưa hết câu
            if ($doan && ! preg_match('/[.!?…:]["”)]?$/u', $doan[count($doan) - 1])) {
                $doan[count($doan) - 1] .= ' '.$chu;
            } else {
                $doan[] = $chu;
            }
        }

        return [$doan ? implode("\n\n", $doan) : null, $noiBat, $chiNguoiLon];
    }

    /**
     * Bảng "Lịch bay từ Hà Nội | Lịch bay từ Hồ Chí Minh | Lịch bay từ Đà Nẵng"
     * + các hàng chuyến bay. Hàng PDF có khi dính hai ô làm một → tách theo mẫu
     * chuyến bay "SQ 191 HANSIN 1235 1705" rồi chia lần lượt cho từng nơi.
     *
     * @return array{0: list<array{city: string, flights: list<string>}>, 1: array<int, true>} nơi đi + các mục đã dùng
     */
    private function lichBay(): array
    {
        foreach ($this->ds as $i => $m) {
            if ($m['loai'] !== 'chu' || ! $m['o'] || ! preg_match_all('/Lịch bay từ\s+([^|]+)/iu', $m['chu'], $k)) {
                continue;
            }
            $noi = array_map(fn ($x) => ['city' => self::tenNoi(trim($x)), 'flights' => []], $k[1]);
            $daDung = [$i => true];
            for ($j = $i + 1; $j < count($this->ds); $j++) {
                $h = $this->ds[$j];
                if ($h['loai'] !== 'chu' || ! preg_match_all('/\b([A-Z0-9]{2})\s?(\d{2,4})\s+([A-Z]{6})\s+(\d{4})\s+(\d{4})\b/', $h['chu'], $cb, PREG_SET_ORDER)) {
                    break;
                }
                foreach ($cb as $c => $x) {
                    if (isset($noi[$c])) {
                        $noi[$c]['flights'][] = "{$x[1]} {$x[2]} {$x[3]} {$x[4]} {$x[5]}";
                    }
                }
                $daDung[$j] = true;
            }

            return [$noi, $daDung];
        }

        return [[], []];
    }

    private static function tenNoi(string $s): string
    {
        $k = self::khoa($s);

        return match (true) {
            (bool) preg_match('/HO CHI MINH|HCM|SAI GON/', $k) => 'TP.HCM',
            str_contains($k, 'HA NOI') => 'Hà Nội',
            str_contains($k, 'DA NANG') => 'Đà Nẵng',
            default => mb_convert_case(mb_strtolower($s), MB_CASE_TITLE),
        };
    }

    /**
     * Nơi khởi hành + phụ thu USD mỗi khách. Giá trong bảng là giá đi Hà Nội;
     * "Khách khởi hành từ TP HCM phụ thu $100 - $120" → TP.HCM phụ thu $100
     * (mức thấp nhất, báo nhân viên xem lại). Nơi khác không ghi phụ thu →
     * để trống (website ghi "liên hệ").
     *
     * @param  list<array{city: string, flights: list<string>}>  $noiDi
     * @return list<array{city: string, surcharge_usd: ?int, flights: string}>
     */
    private function noiKhoiHanh(array $noiDi, string $toan): array
    {
        $phuThu = null;
        if (preg_match('/(?:TP\.?\s*HCM|TPHCM|Hồ Chí Minh)\s+phụ\s*thu\s*\$\s*(\d{2,4})(?:\s*[-–]\s*\$?\s*(\d{2,4}))?/iu', $toan, $k)) {
            $phuThu = (int) $k[1];
            if (! empty($k[2]) && (int) $k[2] !== $phuThu) {
                $this->canhBao[] = "Phụ thu khởi hành từ TP.HCM trong file là \${$k[1]} – \${$k[2]}/khách: đã lấy mức thấp nhất \${$phuThu} — sửa lại nếu cần.";
            }
        }
        if (! $noiDi) {
            if (preg_match('/KHỞI HÀNH TỪ\s+([^\n]+)/u', $toan, $k)) {
                foreach (preg_split('/\s*(?:[–\-\/,&]|\bvà\b)\s*/u', trim($k[1])) as $x) {
                    if (trim($x) !== '') {
                        $noiDi[] = ['city' => self::tenNoi(trim($x)), 'flights' => []];
                    }
                }
            }
            if (! $noiDi) {
                $noiDi = [['city' => 'Hà Nội', 'flights' => []]];
                if ($phuThu !== null) {
                    $noiDi[] = ['city' => 'TP.HCM', 'flights' => []];
                }
            }
        }

        $ra = [];
        foreach ($noiDi as $n) {
            $tien = match ($n['city']) {
                'TP.HCM' => $phuThu,
                'Hà Nội' => 0,
                default => null,
            };
            if ($tien === null) {
                $this->canhBao[] = "Chưa thấy phụ thu khởi hành từ {$n['city']} — website ghi \"liên hệ\" cho tới khi điền số.";
            }
            $ra[] = ['city' => $n['city'], 'surcharge_usd' => $tien, 'flights' => implode("\n", $n['flights'])];
        }

        return $ra;
    }

    // ===================================================================
    // Lịch trình
    // ===================================================================

    /** @return list<array{i: int, so: int, tieu_de: string, bua: ?string}> các tiêu đề ngày, số tăng dần từ 1 */
    private function cacNgay(): array
    {
        $ngay = [];
        foreach ($this->ds as $i => $m) {
            if ($m['loai'] !== 'chu' || $m['gach'] || ! preg_match(self::NGAY, $this->chuCua($m), $k) || (int) $k[1] !== count($ngay) + 1) {
                continue;
            }
            $tieuDe = trim($k[2]);
            $bua = null;
            if (preg_match('/^(.*?)\s*\(([^()]*(?:bữa|ăn|đồ uống)[^()]*)\)\s*$/iu', $tieuDe, $b)) {
                [$tieuDe, $bua] = [trim($b[1]), self::thuong(preg_replace('/\s*\.\s*/u', ', ', trim($b[2], ' .')))];
            }
            $ngay[] = ['i' => $i, 'so' => (int) $k[1], 'tieu_de' => rtrim($tieuDe, ' :'), 'bua' => $bua];
        }

        return $ngay;
    }

    /**
     * Nội dung một ngày: các dòng lịch trình + khung tiện ích resort (tách ra)
     * + "CHI PHÍ KHÔNG BAO GỒM" riêng của ngày (đưa vào phần không bao gồm) + ảnh.
     *
     * @return array{0: list<string>, 1: list<array>, 2: list<string>, 3: list<array>}
     */
    private function noiDungNgay(int $tu, int $den): array
    {
        $dong = [];
        $tienIch = [];
        $khongBaoGom = [];
        $anh = [];
        $nhom = null;      // khung tiện ích một cột đang đọc
        $trongKbg = false; // đang đọc "CHI PHÍ KHÔNG BAO GỒM" của ngày
        $sauTienIch = false; // ảnh ngay sau / trong khung tiện ích là ảnh resort, không phải ảnh của ngày
        for ($i = $tu; $i < $den; $i++) {
            $m = $this->ds[$i];
            if ($m['loai'] === 'anh') {
                $anh[] = $m + ($sauTienIch || $nhom !== null ? ['cua_resort' => true] : []);

                continue;
            }
            if ($m['loai'] === 'nhom_tien_ich') {
                array_push($tienIch, ...$m['nhom']);
                $nhom = null;
                $sauTienIch = true;

                continue;
            }
            if ($m['loai'] !== 'chu') {
                continue;
            }
            $chu = $m['o'] ? implode(' · ', array_filter(array_map(fn ($x) => trim(preg_replace(self::GACH, '', $x)), $m['o']))) : $m['chu'];
            $td = $this->tieuDePhan($m);
            if ($td && $td['loai'] === 'khong_bao_gom') {
                $trongKbg = true;
                $nhom = null;

                continue;
            }
            if ($trongKbg) {
                if ($m['gach']) {
                    $khongBaoGom[] = $chu;

                    continue;
                }
                $trongKbg = false;
            }
            if ($this->laTieuDeTienIch($m)) {
                $tienIch[] = ['title' => $this->cau($chu), 'items' => []];
                $nhom = count($tienIch) - 1;
                $sauTienIch = true;

                continue;
            }
            // Khung tiện ích một cột kết thúc ở mốc giờ lịch trình ("18h30 – 21h00: …") / "Nghỉ đêm…"
            if ($nhom !== null && (preg_match('/^\d{1,2}\s*h\s*\d{0,2}\b|^nghỉ\s+(?:đêm|\d+\s*đêm)/iu', $chu))) {
                $nhom = null;
            }
            if ($nhom !== null) {
                $cuoi = array_key_last($tienIch[$nhom]['items']);
                if ($cuoi !== null && ! $m['gach'] && preg_match('/^\p{Ll}/u', $chu)) {
                    $tienIch[$nhom]['items'][$cuoi] .= ' '.$chu;
                } else {
                    $tienIch[$nhom]['items'][] = $chu;
                }

                continue;
            }
            // dòng chữ thường nối tiếp câu của dòng trên (PDF ngắt dòng khi đổi kiểu chữ)
            $cuoi = array_key_last($dong);
            if ($cuoi !== null && ! $m['gach'] && preg_match('/^\p{Ll}/u', $chu) && ! preg_match('/[.!?:]["”)]?$/u', $dong[$cuoi])) {
                $dong[$cuoi] .= ' '.$chu;

                continue;
            }
            $dong[] = ($m['gach'] ? '• ' : '').$chu;
            $sauTienIch = false;
        }

        return [$dong, $tienIch, $khongBaoGom, $anh];
    }

    /** Tiêu đề khung tiện ích resort một cột: "THÔNG TIN NHÀ HÀNG", "TOUR/ HOẠT ĐỘNG GIẢI TRÍ (đặt…)" */
    private function laTieuDeTienIch(array $m): bool
    {
        if ($m['loai'] !== 'chu' || $m['o'] || $m['gach'] || mb_strlen($m['chu']) > 90 || preg_match('/:\s*\p{Ll}/u', $m['chu'])) {
            return false; // "BANDHI BAR (10:00 – 24:00): nằm gần hồ bơi…" là một mục, không phải tiêu đề
        }
        $truocNgoac = trim(preg_replace('/\(.*$/u', '', $m['chu']));
        $khoa = self::khoa($truocNgoac);

        return $truocNgoac !== '' && self::hoa($truocNgoac) && mb_strlen($truocNgoac) >= 6 && preg_match(self::TIEN_ICH, $khoa)
            && ! preg_match('/^(NGAY\s*\d|LICH TRINH|GIA\b|DICH VU (DA |KHONG )|CHI PHI)/', $khoa);
    }

    private function luuTru(?int $iLichTrinh, int $dauNgay): ?string
    {
        if ($iLichTrinh === null) {
            return null;
        }
        $m = $this->ds[$iLichTrinh];
        $con = trim(preg_replace('/^LỊCH TRÌNH\s*:?\s*/iu', '', $m['chu']));
        if ($con === '' && $iLichTrinh + 1 < $dauNgay && $this->ds[$iLichTrinh + 1]['loai'] === 'chu') {
            $con = $this->ds[$iLichTrinh + 1]['chu'];
        }

        return $con !== '' && preg_match('/đêm|night/iu', $con) ? Str::limit($this->cau($con), 250, '') : null;
    }

    // ===================================================================
    // Các phần ngoài lịch trình
    // ===================================================================

    /**
     * Tiêu đề một phần (so chữ không dấu). 'con' = chữ đi liền sau dấu hai
     * chấm trên cùng dòng ("LƯU Ý ĐẶC BIỆT: Combo được xây dựng…").
     *
     * @return ?array{loai: string, ten: string, con: string}
     */
    private function tieuDePhan(array $m): ?array
    {
        if ($m['loai'] === 'bang_gia') {
            return ['loai' => 'gia', 'ten' => '', 'con' => ''];
        }
        if ($m['loai'] !== 'chu' || $m['gach']) {
            return null;
        }
        $chu = $this->chuCua($m);
        [$dau, $con] = array_pad(preg_split('/\s*:\s*/u', $chu, 2), 2, '');
        $khoa = self::khoa($dau);
        if (! self::hoa($dau) && ! $m['dam'] && ! preg_match('/^NOTE$/', $khoa)) {
            return null;
        }
        $loai = match (true) {
            (bool) preg_match('/GIA COMBO|^BANG GIA/', $khoa) => 'gia',
            (bool) preg_match('/^(DICH VU|CHI PHI|GIA)\b.{0,20}KHONG BAO GOM/', $khoa) => 'khong_bao_gom',
            (bool) preg_match('/^(DICH VU|CHI PHI|GIA)\b.{0,20}BAO GOM/', $khoa) => 'bao_gom',
            (bool) preg_match('/^RESORT\b.*(18 TUOI|NGUOI LON)/', $khoa) => 'nguoi_lon',
            (bool) preg_match('/^(QUY DINH|THONG TIN VE RESORT|CHINH SACH|DIEU KHOAN)/', $khoa) => 'quy_dinh',
            (bool) preg_match('/^THONG TIN PHONG/', $khoa) => 'phong',
            (bool) preg_match('/^(LUU Y DAC BIET|LUU Y CHUNG|LUU Y|GHI CHU|NOTE)$/', $khoa) => 'luu_y',
            default => null,
        };
        if ($loai === null || mb_strlen($dau) > 100) {
            return null;
        }

        return ['loai' => $loai, 'ten' => $this->cau($dau), 'con' => trim($con)];
    }

    /**
     * Đọc các phần ngoài lịch trình theo tiêu đề.
     *
     * @return array{bang: ?array, bao_gom: list<string>, khong_bao_gom: list<string>, luu_y: list<array>, quy_dinh: list<array>, tien_ich: list<array>, chi_nguoi_lon: bool}
     */
    private function cacPhan(int $hetBia, int $dauNgay, int $cuoiLichTrinh, array $daDung): array
    {
        $ra = ['bang' => null, 'bao_gom' => [], 'khong_bao_gom' => [], 'luu_y' => [], 'quy_dinh' => [], 'tien_ich' => [], 'chi_nguoi_lon' => false];
        $phan = null;
        $luuY = [];      // tiêu đề → các dòng
        $dongWord = [];  // hàng bảng giá file Word
        $nhom = null;
        $ghiChuGia = 'Ghi chú bảng giá';
        $nhieuCot = 0; // số hàng nhiều cột lọt vào phần lưu ý
        foreach ($this->ds as $i => $m) {
            if (($i < $hetBia) || ($i >= $dauNgay && $i < $cuoiLichTrinh) || isset($daDung[$i])) {
                continue;
            }
            if ($m['loai'] === 'bang_gia') {
                $ra['bang'] = ['cot' => $m['cot'], 'hang' => $m['hang']];
                $phan = 'ghi_chu_gia';

                continue;
            }
            if ($m['loai'] === 'nhom_tien_ich') {
                array_push($ra['tien_ich'], ...$m['nhom']);

                continue;
            }
            if ($m['loai'] !== 'chu') {
                continue;
            }
            $td = $this->tieuDePhan($m);
            if ($td) {
                $phan = $td['loai'];
                $nhom = null;
                if ($phan === 'nguoi_lon') {
                    $ra['chi_nguoi_lon'] = true;
                }
                if (in_array($phan, ['luu_y', 'nguoi_lon'], true)) {
                    $phan = 'luu_y:'.($td['ten'] === 'Note' ? $ghiChuGia : $td['ten']);
                }
                if ($phan === 'phong') {
                    $ra['quy_dinh'][] = ['title' => $td['ten'], 'content' => ''];
                    $phan = 'quy_dinh';
                }
                if ($td['con'] !== '') {
                    $m = ['chu' => $td['con'], 'o' => null, 'gach' => false] + $m;
                } else {
                    continue;
                }
            }
            $chu = $m['o'] ? implode(' · ', array_filter($m['o'])) : $m['chu'];
            if (preg_match('/phụ\s*thu\s*\$/iu', $chu) && preg_match('/khởi hành từ/iu', $chu)) {
                continue; // phụ thu theo nơi khởi hành — đã đọc riêng
            }
            if ($phan === 'gia') {
                if ($m['o']) {
                    $dongWord[] = $m['o'];

                    continue;
                }
                if ($dongWord) {
                    $phan = 'ghi_chu_gia'; // hết bảng (file Word)
                }
            }
            if (preg_match('/DANH CHO NGUOI LON|TU 18 TUOI/', self::khoa($chu)) && self::hoa($chu)) {
                $ra['chi_nguoi_lon'] = true;
            }
            if ($this->laTieuDeTienIch($m) && ! in_array($phan, ['bao_gom', 'khong_bao_gom', 'quy_dinh'], true)) {
                $ra['tien_ich'][] = ['title' => $this->cau($chu), 'items' => []];
                $nhom = count($ra['tien_ich']) - 1;

                continue;
            }
            if ($nhom !== null) {
                $ra['tien_ich'][$nhom]['items'][] = $chu;

                continue;
            }
            switch (true) {
                case in_array($phan, ['bao_gom', 'khong_bao_gom'], true):
                    if (preg_match('/^lưu ý\s*:/iu', $chu)) {
                        $luuY['Lưu ý về dịch vụ'][] = trim(preg_replace('/^lưu ý\s*:\s*/iu', '', $chu));
                        $phan = 'luu_y:Lưu ý về dịch vụ';
                        break;
                    }
                    $cuoi = array_key_last($ra[$phan]);
                    if ($cuoi !== null && ! $m['gach'] && preg_match('/^\p{Ll}/u', $chu)) {
                        $ra[$phan][$cuoi] .= ' '.$chu;
                    } else {
                        $ra[$phan][] = $chu;
                    }
                    break;
                case $phan === 'quy_dinh':
                    // "1. Chứng từ…", "5  Ưu đãi Tuần trăng mật…" (không phải "02 ADL + 02 Kids" trong bảng phòng)
                    if (! $m['gach'] && ! $m['o'] && preg_match('/^(\d{1,2})(?:\s*[.)]\s*|\s+(?=\p{Lu}\p{Ll}))(\p{Lu}.*)$/u', $chu, $k)) {
                        [$tieuDe, $noiDung] = array_pad(preg_split('/\s*:\s*/u', rtrim($k[2], ' :'), 2), 2, '');
                        $ra['quy_dinh'][] = ['title' => Str::limit($tieuDe, 250, ''), 'content' => trim($noiDung)];
                        break;
                    }
                    if (! $ra['quy_dinh']) {
                        $ra['quy_dinh'][] = ['title' => 'Thông tin chung', 'content' => ''];
                    }
                    $cuoi = array_key_last($ra['quy_dinh']);
                    $ra['quy_dinh'][$cuoi]['content'] = ltrim($ra['quy_dinh'][$cuoi]['content']."\n".($m['gach'] ? '• ' : '').$chu);
                    break;
                case $phan === 'ghi_chu_gia' || $phan === 'gia':
                    $luuY[$ghiChuGia][] = $chu;
                    $nhieuCot += $m['o'] ? 1 : 0;
                    break;
                case is_string($phan) && str_starts_with($phan, 'luu_y:'):
                    $luuY[substr($phan, 6)][] = $chu;
                    $nhieuCot += $m['o'] ? 1 : 0;
                    break;
                default:
                    // chữ ngoài mọi phần (sau trang bìa, trước lịch trình) — bỏ qua
                    break;
            }
        }

        if (! $ra['bang'] && $dongWord) {
            $ra['bang'] = $this->bangWord($dongWord);
        }
        foreach ($luuY as $ten => $dong) {
            $ra['luu_y'][] = ['title' => $ten, 'content' => implode("\n", $dong)];
        }
        if ($nhieuCot >= 2) {
            $this->canhBao[] = 'Phần ghi chú / ưu đãi sau bảng giá chia nhiều cột, chữ các cột có thể lẫn vào nhau — xem lại mục "Lưu ý" của combo.';
        }

        return $ra;
    }

    /**
     * Bảng giá trong file Word: hàng tiêu đề có ngày khởi hành ở các ô sau ô
     * đầu ("04/11/2026", "08, 09/02/2027 (Lịch Tết)"), hàng tiêu đề phụ
     * ("TWN/DBL") nối vào cột; mỗi hàng giá: ô đầu = tên phòng (+ sức chứa
     * dòng dưới), các ô sau = giá USD hoặc "NA".
     *
     * @param  list<list<string>>  $hang
     */
    private function bangWord(array $hang): ?array
    {
        $cot = null;
        $ra = [];
        foreach ($hang as $o) {
            $o = array_values($o);
            $gia = [];
            foreach (array_slice($o, 1) as $c => $x) {
                $x = trim(str_replace(['$', ' '], '', $x));
                if (preg_match('/^\d{1,2}[.,]\d{3}$/', $x)) {
                    $gia[$c] = (int) preg_replace('/\D/', '', $x);
                } elseif (preg_match('/^(N\/?A|-|—)$/i', $x)) {
                    $gia[$c] = null;
                }
            }
            if (! $gia) {
                if ($ra) {
                    break; // hết bảng
                }
                if ($cot === null && preg_grep('/\d{1,2}\s*\/\s*\d{1,2}/u', array_slice($o, 1))) {
                    $cot = array_map(fn ($x) => str_replace("\n", ' ', $x), array_slice($o, 1));
                } elseif ($cot !== null) {
                    foreach (array_slice($o, 1) as $c => $x) {
                        $cot[$c] = trim(($cot[$c] ?? '').' '.str_replace("\n", ' ', $x));
                    }
                }

                continue;
            }
            if ($cot === null) {
                continue;
            }
            $ra[] = ['dong' => array_values(array_filter(array_map('trim', explode("\n", $o[0])))), 'gia' => array_filter($gia, fn ($c) => $c < count($cot), ARRAY_FILTER_USE_KEY)];
        }

        return $cot && $ra ? ['cot' => $cot, 'hang' => $ra] : null;
    }

    /**
     * Bảng giá → hạng phòng + ngày khởi hành. Một cột ghi hai ngày ("08, 09/02/2027
     * (Tết ÂL)") thành hai ngày cùng giá. Ngày đã qua thì bỏ.
     *
     * @return array{0: list<array{ten: string, suc_chua: ?string, chi_nguoi_lon: bool}>, 1: list<array{ngay: string, nhan: ?string, gia: array<int, ?int>}>}
     */
    private function bangGia(?array $bang, bool $chiNguoiLon): array
    {
        if (! $bang) {
            $this->canhBao[] = 'Không tìm thấy bảng giá — thêm ngày khởi hành và giá ở mục "Ngày khởi hành & bảng giá".';

            return [[], []];
        }

        $phong = [];
        foreach ($bang['hang'] as $h) {
            $sucChua = [];
            $ten = [];
            foreach ($h['dong'] as $d) {
                if (preg_match('/^\(?\s*\d{1,2}\s*(?:ADL|Adults?|người lớn)\b/iu', $d)) {
                    $sucChua[] = trim($d, ' ()');
                } else {
                    $ten[] = $d;
                }
            }
            $ten = trim(preg_replace('/\s+/u', ' ', implode(' ', $ten)));
            $chiLon = $chiNguoiLon;
            if (preg_match('/\(?\s*(?:adults?\s*only|chỉ\s*dành\s*cho\s*người\s*lớn[^)]*)\s*\)?/iu', $ten)) {
                $chiLon = true;
                $ten = trim(preg_replace('/\(?\s*(?:adults?\s*only|chỉ\s*dành\s*cho\s*người\s*lớn[^)]*)\s*\)?/iu', '', $ten));
            }
            // sức chứa trong ngoặc cuối tên: "Two bedroom … Villa (4ADL + 2CHD/6 ADL)"
            if (preg_match('/\s*\(([^()]*\d\s*(?:ADL|Adults?|CHD|Child)[^()]*)\)\s*$/iu', $ten, $k)) {
                $sucChua[] = $k[1];
                $ten = trim(substr($ten, 0, -strlen($k[0])));
            }
            if ($ten === '') {
                $this->canhBao[] = 'Có một hàng giá không đọc được tên hạng phòng — kiểm tra bảng giá.';

                continue;
            }
            $phong[] = ['ten' => Str::limit($ten, 250, ''), 'suc_chua' => $sucChua ? self::sucChua(implode(' / ', $sucChua)) : null, 'chi_nguoi_lon' => $chiLon, 'gia' => $h['gia']];
        }

        $ngay = [];
        $daQua = [];
        foreach ($bang['cot'] as $c => $tieuDe) {
            $chu = trim(preg_replace('/\b(?:TWN|DBL|TPL|SGL|TRP)\b(?:\s*\/\s*\b(?:TWN|DBL|TPL|SGL|TRP)\b)*/i', '', $tieuDe));
            $nhan = preg_match('/\(([^()]+)\)/u', $chu, $k) ? trim(strtr($k[1], ['ÂL' => 'Âm lịch', 'DL' => 'Dương lịch'])) : null;
            $chu = preg_replace('/\s*\/\s*/u', '/', $chu);
            $cacNgay = [];
            if (preg_match_all('/(\d{1,2}(?:\s*[,;&]\s*\d{1,2})*)\/(\d{1,2})(?:\/(\d{2,4}))?/u', $chu, $kq, PREG_SET_ORDER)) {
                foreach ($kq as $x) {
                    $nam = isset($x[3]) && $x[3] !== '' ? (int) (strlen($x[3]) === 2 ? '20'.$x[3] : $x[3]) : null;
                    foreach (preg_split('/\s*[,;&]\s*/', $x[1]) as $d) {
                        $d = (int) $d;
                        $t = (int) $x[2];
                        if (! checkdate($t, $d, $nam ?? 2000)) {
                            continue;
                        }
                        // không ghi năm: tháng đã qua thì là năm sau
                        $n = $nam ?? (Carbon::create(today()->year, $t, $d)->lt(today()->subDays(30)) ? today()->year + 1 : today()->year);
                        $cacNgay[] = Carbon::create($n, $t, $d)->toDateString();
                    }
                }
            }
            if (! $cacNgay) {
                $this->canhBao[] = 'Không đọc được ngày khởi hành ở cột "'.$tieuDe.'" của bảng giá — thêm tay ngày này.';

                continue;
            }
            $gia = [];
            foreach ($phong as $p => $x) {
                $gia[$p] = $x['gia'][$c] ?? null;
            }
            foreach ($cacNgay as $d) {
                if (Carbon::parse($d)->lt(today())) {
                    $daQua[] = Carbon::parse($d)->format('d/m/Y');

                    continue;
                }
                $ngay[$d] = ['ngay' => $d, 'nhan' => $nhan, 'gia' => $gia];
            }
        }
        ksort($ngay);
        if ($daQua) {
            $this->canhBao[] = 'Bỏ qua '.count($daQua).' ngày khởi hành đã qua: '.implode(', ', $daQua).'.';
        }
        foreach ($phong as $x) {
            if (! array_filter($x['gia'], fn ($v) => $v !== null)) {
                $this->canhBao[] = "Hạng phòng \"{$x['ten']}\" ghi NA mọi ngày (không bán) — vẫn thêm vào resort, ngoài web không hiện.";
            }
        }

        return [array_map(fn ($x) => array_diff_key($x, ['gia' => 1]), $phong), array_values($ngay)];
    }

    /** "03 Adults/02 Adults + 01 Child" → "3 người lớn hoặc 2 người lớn + 1 trẻ em" */
    private static function sucChua(string $s): string
    {
        $phan = [];
        foreach (preg_split('/\s*\/\s*/u', $s) as $x) {
            $x = preg_replace_callback('/0*(\d+)\s*(?:ADL|Adults?|người lớn)\b/iu', fn ($k) => $k[1].' người lớn', $x);
            $x = preg_replace_callback('/0*(\d+)\s*(?:CHD|Child(?:ren)?|Kids?|trẻ em)\b/iu', fn ($k) => $k[1].' trẻ em', $x);
            $x = trim(preg_replace('/\s*\+\s*/u', ' + ', $x));
            if ($x !== '') {
                $phan[] = $x;
            }
        }

        return Str::limit(implode(' hoặc ', $phan), 250, '');
    }

    // ===================================================================
    // Thông tin khác
    // ===================================================================

    private function goiDichVu(string $s): ?string
    {
        return match (true) {
            (bool) preg_match('/WOW\s*!?\s*PREMIUM\s+ALL[\s-]*INCLUSIVE/i', $s) => 'WOW Premium All Inclusive',
            (bool) preg_match('/WOW\s*!?\s*ALL[\s-]*INCLUSIVE/i', $s) => 'WOW All Inclusive',
            (bool) preg_match('/PREMIUM\s+ALL[\s-]*INCLUSIVE/i', $s) => 'Premium All Inclusive',
            (bool) preg_match('/ALL[\s-]*INCLUSIVE/i', $s) => 'All Inclusive',
            (bool) preg_match('/FULL\s*BOARD/i', $s) => 'Full Board',
            (bool) preg_match('/HALF\s*BOARD/i', $s) => 'Half Board',
            default => null,
        };
    }

    /** Cách ra resort: "Vận chuyển: …" trên trang bìa, không thì dịch vụ bao gồm / từ khoá trong file */
    private function vanChuyen(string $bia, array $baoGom, string $toan): ?string
    {
        if (preg_match('/Vận chuyển\s*:\s*([^\n]+?)(?=\s+(?:(?:Thời gian|Khởi hành|Vị trí|Hàng không|Lưu trú)\s*:|Gói\s)|\n|$)/iu', $bia, $k)) {
            return self::thuong($k[1]);
        }
        $nguon = implode("\n", array_filter($baoGom, fn ($x) => preg_match('/resort/iu', $x) && preg_match('/vận chuyển|phương tiện/iu', $x)))."\n".$bia;
        foreach ([$nguon, $toan] as $s) {
            $khoa = self::khoa($s);
            $loai = array_filter([
                'Thuỷ phi cơ' => substr_count($khoa, 'THUY PHI CO') + substr_count($khoa, 'SEAPLANE'),
                'Tàu cao tốc' => substr_count($khoa, 'TAU CAO TOC') + substr_count($khoa, 'SPEEDBOAT') + substr_count($khoa, 'CANO CAO TOC'),
            ]);
            if ($loai) {
                arsort($loai);

                return array_key_first($loai);
            }
        }

        return null;
    }

    private function dao(string $toan, string $bia): ?string
    {
        if (preg_match('/\b((?:North|South)\s+)?(?<![\p{Ll}])([A-Z][\p{L}]+)\s+Atoll\b/u', $toan, $k)) {
            return trim($k[1].$k[2]).' Atoll';
        }
        if (preg_match('/Vị trí\s*:\s*([^\n]+?)(?=\s+[-–]\s+|\s+(?:Vận chuyển|Thời gian)\s*:|\n|$)/iu', $bia, $k)) {
            return Str::limit(trim($k[1]), 100, '');
        }

        return null;
    }

    /** Mô tả ngắn cho Google (120–160 ký tự) dựng từ thông tin trong file */
    private function tomTat(array $resort, ?string $sao, ?string $dao, ?int $soNgay, ?int $soDem, ?string $luuTru, ?string $hangBay, ?string $vanChuyen): string
    {
        $s = 'Combo Maldives '.($soNgay ? "{$soNgay} ngày {$soDem} đêm " : '').'nghỉ dưỡng '.$resort['ten'].($sao ? " {$sao}*" : '')
            .($dao ? " ({$dao})" : '').': '
            .implode(', ', array_filter([$luuTru ? mb_strtolower(mb_substr($luuTru, 0, 1)).mb_substr($luuTru, 1) : null,
                $hangBay ? 'bay '.$hangBay : null, $vanChuyen ? mb_strtolower($vanChuyen).' ra resort' : null])).'.';

        return Str::limit($s, 300, '…');
    }

    /**
     * Ảnh dùng được: đúng định dạng ảnh, không quá nhỏ (biểu tượng), không trùng.
     *
     * @return list<array{du_lieu: string, rong: int, cao: int, ngay: ?int}>
     */
    private function locAnh(array $anh): array
    {
        $daCo = [];
        $ra = [];
        foreach ($anh as $a) {
            $kt = @getimagesizefromstring($a['du_lieu']);
            if (! $kt || ! in_array($kt[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP, IMAGETYPE_BMP], true)) {
                continue;
            }
            $md5 = md5($a['du_lieu']);
            if (isset($daCo[$md5]) || $kt[0] < 300 || $kt[1] < 200) {
                continue;
            }
            $daCo[$md5] = true;
            $ra[] = ['du_lieu' => $a['du_lieu'], 'rong' => $kt[0], 'cao' => $kt[1], 'ngay' => $a['ngay']];
        }

        return $ra;
    }
}
