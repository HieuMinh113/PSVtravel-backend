<?php

namespace Modules\Alliance\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as NgayExcel;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Đọc file sheet chỗ trống của đối tác liên minh → danh sách ngày khởi hành.
 *
 * Mỗi đối tác tự trình bày sheet theo kiểu riêng và PSV không sửa được, nên
 * bộ đọc KHÔNG dựa vào vị trí cột cố định mà tự nhận diện theo chữ ở dòng tiêu
 * đề ("NGÀY KHỞI HÀNH", "GIÁ", "NHẬN", "SIZE"...). Viết dựa trên 3 mẫu thật:
 *
 *  - V1 Travel: nhiều tab theo nước, mỗi tour một khối ô gộp, dưới là nhiều
 *    dòng ngày đi; có SIZE / SURE / HOLD / NHẬN; giá ghi theo nghìn ("21.990").
 *  - AZ (Nhật): bảng phẳng mỗi dòng một ngày đi, có "SỐ CHỖ CÒN NHẬN", các
 *    dòng "THÁNG 10/2026" chia mục.
 *  - M Tour (Hàn): tên tour nằm ở dòng gộp cả bề ngang, một ô chứa nhiều
 *    ngày ("09/10 – 13/10⏎20/10 – 24/10"), KHÔNG có số chỗ.
 *
 * Mọi suy đoán (năm, đơn vị giá, trạng thái) đều ghi lại chữ gốc trong sheet
 * để điều hành đối chiếu được khi cần.
 */
class DocBangLienMinh
{
    /** Giới hạn để một file lạ không làm treo máy chủ. */
    private const SO_DONG_TOI_DA = 3000;

    private const SO_COT_TOI_DA = 40;

    /** @var array<int,string> cảnh báo trong lúc đọc (tab nào không đọc được...) */
    private array $canhBao = [];

    private Carbon $homNay;

    /**
     * @return array{dong: list<array<string,mixed>>, canh_bao: list<string>, ngay_cap_nhat: ?string}
     */
    public function doc(string $duongDan, ?Carbon $homNay = null): array
    {
        $this->homNay = ($homNay ?? now())->copy()->startOfDay();
        $this->canhBao = [];

        $trinhDoc = IOFactory::createReaderForFile($duongDan);
        // Cần định dạng ô (để nhận ra ô ngày tháng) và link → không bật readDataOnly.
        $trinhDoc->setReadEmptyCells(false);
        $sach = $trinhDoc->load($duongDan);

        $tatCa = [];
        $ngayCapNhatChung = null;
        foreach ($sach->getWorksheetIterator() as $tab) {
            if ($tab->getSheetState() !== Worksheet::SHEETSTATE_VISIBLE) {
                continue;
            }
            [$dong, $capNhat] = $this->docTab($tab);
            $ngayCapNhatChung ??= $capNhat;
            array_push($tatCa, ...$dong);
        }
        $sach->disconnectWorksheets();

        return [
            'dong' => $this->boTrung($tatCa),
            'canh_bao' => $this->canhBao,
            'ngay_cap_nhat' => $ngayCapNhatChung?->toDateString(),
        ];
    }

    // ------------------------------------------------------------------
    // Đọc từng tab
    // ------------------------------------------------------------------

    /** @return array{0: list<array<string,mixed>>, 1: ?Carbon} */
    private function docTab(Worksheet $tab): array
    {
        $luoi = $this->dungLuoi($tab);
        $tenTab = trim($tab->getTitle());
        $namTab = $this->timNam($tenTab);

        $ketQua = [];
        $cot = null;           // vai trò => [chỉ số cột]
        $ngayCapNhat = null;
        $muc = null;           // "THÁNG 10/2026", "TOUR TẾT 2027", "HÀN QUỐC"...
        $namMuc = null;
        $tenNguCanh = null;    // tên tour lấy từ dòng gộp (kiểu M Tour)
        $lanCuoiTheoTour = []; // khoá tour => ngày gần nhất, để suy năm liền mạch
        $linkTheoTour = [];    // link chương trình chỉ ghi ở dòng đầu khối tour (V1)

        // Lưới thưa (bỏ dòng trống) → chạy tới chỉ số dòng cuối, không phải count().
        $soDong = $luoi ? max(array_keys($luoi)) + 1 : 0;
        for ($r = 0; $r < $soDong; $r++) {
            $o = $luoi[$r] ?? [];
            if (! $o) {
                continue;
            }

            // "Ngày cập nhật: 06/10/2026" — dùng làm mốc suy năm
            if ($capNhat = $this->timNgayCapNhat($o)) {
                $ngayCapNhat = $capNhat;
                continue;
            }

            // Dòng tiêu đề (có thể 2 tầng) → xác định lại vai trò từng cột
            if ($moi = $this->nhanDienTieuDe($luoi, $r)) {
                [$cot, $soDongTieuDe] = $moi;
                $r += $soDongTieuDe - 1;
                $muc = null;
                $namMuc = null;
                $tenNguCanh = null;
                continue;
            }
            if (! $cot) {
                continue; // chưa tới bảng
            }

            // Dòng gộp cả bề ngang: tiêu đề mục / tên tour
            if ($chu = $this->laDongNguCanh($o, $cot)) {
                $nam = $this->timNam($chu);
                if ($this->laTieuDeMuc($chu) || isset($cot['ten'])) {
                    $muc = $chu;
                    $namMuc = $nam;
                    $tenNguCanh = null;
                } else {
                    $tenNguCanh = $chu;
                    $namMuc = $nam ?? $namMuc;
                }
                continue;
            }

            $oNgay = $this->o($o, $cot['ngay_di'] ?? []);
            if (! $oNgay) {
                continue;
            }
            $cacNgay = $this->tachNgay($oNgay);
            if (! $cacNgay) {
                continue;
            }

            $thongTin = $this->thongTinDong($o, $cot, $muc, $tenNguCanh);
            $khoaTour = $this->khoaTour($thongTin['ten_tour'], $thongTin['hang_bay']);
            if ($thongTin['link_chuong_trinh']) {
                $linkTheoTour[$khoaTour] ??= $thongTin['link_chuong_trinh'];
            } else {
                $thongTin['link_chuong_trinh'] = $linkTheoTour[$khoaTour] ?? null;
            }
            $namThang = $this->timNamTrongOThang($this->chu($o, $cot['thang'] ?? []));
            $moc = $ngayCapNhat ?? $this->homNay;

            foreach ($cacNgay as $ng) {
                $ngay = $this->chotNam(
                    $ng,
                    $namThang,
                    $namMuc ?? $namTab,
                    $moc,
                    $lanCuoiTheoTour[$khoaTour] ?? null,
                );
                if (! $ngay) {
                    continue;
                }
                $lanCuoiTheoTour[$khoaTour] = $ngay;

                // Chỉ giữ ngày đi từ hôm nay trở đi (sheet thường giữ cả lịch
                // cũ từ đầu năm), và bỏ ngày vô lý quá xa.
                if ($ngay->lt($this->homNay) || $ngay->gt($this->homNay->copy()->addYears(2))) {
                    continue;
                }

                $ketQua[] = $thongTin + [
                    'tab' => $tenTab,
                    'muc' => $muc,
                    'khoa_tour' => $khoaTour,
                    'ngay_di' => $ngay->toDateString(),
                    'dong_sheet' => $r + 1,
                ];
            }
        }

        if ($cot === null && $soDong > 0) {
            $this->canhBao[] = "Tab “{$tenTab}”: không tìm thấy dòng tiêu đề có cột ngày khởi hành.";
        }

        return [$ketQua, $ngayCapNhat];
    }

    /**
     * Lưới ô của tab: [dòng][cột] => ['chu' => chữ hiển thị, 'so' => số|null,
     * 'ngay' => Carbon|null, 'link' => url|null]. Ô gộp được chép giá trị ra
     * mọi ô con — nhờ vậy dòng nào cũng "thấy" tên tour / giá của khối gộp.
     */
    private function dungLuoi(Worksheet $tab): array
    {
        $luoi = [];
        $cuoi = min($tab->getHighestDataRow(), self::SO_DONG_TOI_DA);
        $cotCuoi = min(Coordinate::columnIndexFromString($tab->getHighestDataColumn()), self::SO_COT_TOI_DA);

        foreach ($tab->getRowIterator(1, max(1, $cuoi)) as $dong) {
            $iter = $dong->getCellIterator('A', Coordinate::stringFromColumnIndex($cotCuoi));
            $iter->setIterateOnlyExistingCells(true);
            foreach ($iter as $o) {
                $gt = $this->giaTriO($o);
                if ($gt !== null) {
                    $luoi[$dong->getRowIndex() - 1][Coordinate::columnIndexFromString($o->getColumn()) - 1] = $gt;
                }
            }
        }

        foreach ($tab->getMergeCells() as $vung) {
            [$dau, $cuoiVung] = Coordinate::rangeBoundaries($vung);
            $goc = $luoi[$dau[1] - 1][$dau[0] - 1] ?? null;
            if (! $goc) {
                continue;
            }
            $goc['gop'] = $vung;
            $goc['rong'] = $cuoiVung[0] - $dau[0] + 1;
            for ($y = $dau[1]; $y <= min($cuoiVung[1], $cuoi); $y++) {
                for ($x = $dau[0]; $x <= min($cuoiVung[0], $cotCuoi); $x++) {
                    $luoi[$y - 1][$x - 1] = $goc;
                }
            }
        }

        ksort($luoi);
        foreach ($luoi as &$hang) {
            ksort($hang);
        }

        return $luoi;
    }

    private function giaTriO(Cell $o): ?array
    {
        try {
            $tho = $o->getValue();
            if ($o->isFormula()) {
                // Không tự tính công thức (chậm, dễ lỗi): lấy kết quả sheet đã lưu sẵn.
                $tho = $o->getOldCalculatedValue();
            }
            $chu = trim((string) $o->getFormattedValue());
            if ($o->isFormula()) {
                $chu = trim((string) $tho);
            }
        } catch (\Throwable) {
            return null;
        }
        if ($chu === '' || $chu === '#N/A' || $chu === '#REF!') {
            return null;
        }

        $ngay = null;
        if (is_numeric($tho) && NgayExcel::isDateTime($o)) {
            try {
                $ngay = Carbon::instance(NgayExcel::excelToDateTimeObject((float) $tho))->startOfDay();
            } catch (\Throwable) {
                $ngay = null;
            }
        }

        $link = $o->hasHyperlink() ? trim($o->getHyperlink()->getUrl()) : null;
        if (! $link && is_string($o->getValue()) && preg_match('/HYPERLINK\(\s*"([^"]+)"/i', $o->getValue(), $m)) {
            $link = $m[1];
        }

        return [
            'chu' => str_replace("\r", '', $chu),
            'so' => is_numeric($tho) ? (float) $tho : null,
            'ngay' => $ngay,
            'link' => $link && preg_match('#^https?://#i', $link) ? $link : null,
        ];
    }

    // ------------------------------------------------------------------
    // Nhận diện tiêu đề & vai trò cột
    // ------------------------------------------------------------------

    /**
     * @return array{0: array<string, list<int>>, 1: int}|null [vai trò => cột, số dòng tiêu đề]
     */
    private function nhanDienTieuDe(array $luoi, int $r): ?array
    {
        $tren = $luoi[$r] ?? [];
        // Dòng tiêu đề không chứa ngày nào (dòng dữ liệu V1 cũng có chữ "Ngày
        // khởi hành là...", "Tháng 4:", "Giá cũ..." — dễ nhầm là tiêu đề).
        if (count($this->vaiTroCuaDong($tren)) < 3 || $this->dongCoNgay($tren)) {
            return null;
        }

        // Tiêu đề 2 tầng: "LỊCH KHỞI HÀNH" ở trên, "THÁNG | NGÀY" ở dưới
        $duoi = $luoi[$r + 1] ?? [];
        $haiTang = $duoi
            && count($this->vaiTroCuaDong($duoi)) >= 2
            && ! $this->dongCoNgay($duoi);

        $nhan = [];
        foreach (array_unique(array_merge(array_keys($tren), $haiTang ? array_keys($duoi) : [])) as $c) {
            $a = $tren[$c]['chu'] ?? '';
            $b = $haiTang ? ($duoi[$c]['chu'] ?? '') : '';
            $nhan[$c] = [$this->chuanHoa($a), $this->chuanHoa($b === $a ? '' : $b)];
        }
        ksort($nhan);

        $cot = [];
        foreach ($nhan as $c => [$a, $b]) {
            $vaiTro = $this->vaiTroCot($a, $b);
            if (! $vaiTro) {
                continue;
            }
            // Mỗi vai trò lấy cột đầu tiên; riêng link chương trình gom nhiều cột.
            if ($vaiTro === 'chuong_trinh' || ! isset($cot[$vaiTro])) {
                $cot[$vaiTro][] = $c;
            }
        }

        if (! isset($cot['ngay_di'])) {
            return null;
        }

        return [$cot, $haiTang ? 2 : 1];
    }

    private function vaiTroCuaDong(array $o): array
    {
        $vt = [];
        foreach ($o as $x) {
            if (mb_strlen($x['chu']) > 30 || $x['ngay']) {
                continue;
            }
            if ($v = $this->vaiTroCot($this->chuanHoa($x['chu']), '')) {
                $vt[$v] = true;
            }
        }

        return array_keys($vt);
    }

    /** Vai trò của cột theo nhãn tầng trên ($a) và tầng dưới ($b), đã chuẩn hoá. */
    private function vaiTroCot(string $a, string $b): ?string
    {
        $du = trim("$a $b");
        $cuoi = $b !== '' ? $b : $a;
        $co = fn (string $s, string ...$tu) => (bool) array_filter($tu, fn ($t) => preg_match('/(^| )'.preg_quote($t, '/').'( |$)/', $s));

        if ($du === '' || $co($du, 'CAP NHAT')) {
            return null;
        }
        if ($co($du, 'DEADLINE', 'HAN VISA', 'HAN NOP')) {
            return 'han_visa';
        }
        if ($cuoi === 'STT' || $cuoi === 'TT') {
            return null;
        }
        if ($co($du, 'TRE EM')) {
            return 'gia_tre_em';
        }
        if ($co($du, 'EM BE')) {
            return 'gia_em_be';
        }
        if ($co($cuoi, 'NHAN', 'CON CHO', 'CON LAI', 'CHO TRONG', 'CON NHAN')) {
            return 'con_cho';
        }
        if ($co($cuoi, 'SURE', 'DA CHOT', 'DA BAN', 'DA DAT')) {
            return 'da_chot';
        }
        if ($co($cuoi, 'HOLD', 'GIU CHO', 'DANG GIU')) {
            return 'dang_giu';
        }
        if ($co($cuoi, 'SIZE', 'TONG CHO', 'SO CHO', 'SL KHACH')) {
            return 'tong_cho';
        }
        if ($co($cuoi, 'COM', 'HOA HONG')) {
            return 'hoa_hong';
        }
        // Ngày / tháng: quyết theo tầng dưới ("THÁNG" | "NGÀY") trước
        if ($cuoi === 'THANG' || preg_match('/^THANG( |$)/', $cuoi)) {
            return 'thang';
        }
        if ($co($cuoi, 'NGAY', 'NGAY KHOI HANH', 'NGAY DI', 'KHOI HANH', 'LICH KHOI HANH')) {
            return 'ngay_di';
        }
        if ($co($du, 'GIA', 'NGUOI LON', 'GIA BAN', 'GIA TOUR')) {
            return 'gia';
        }
        if ($co($cuoi, 'TEN', 'TEN TOUR', 'CUNG DUONG', 'HANH TRINH', 'TOUR', 'SAN PHAM')) {
            return 'ten';
        }
        if ($co($cuoi, 'THOI GIAN', 'SO NGAY')) {
            return 'thoi_gian';
        }
        if ($co($cuoi, 'HK', 'HANG KHONG', 'HANG BAY', 'CHUYEN BAY', 'HANG')) {
            return 'hang_bay';
        }
        if ($co($cuoi, 'NOTE', 'GHI CHU', 'LUU Y')) {
            return 'ghi_chu';
        }
        if ($co($du, 'CHUONG TRINH', 'LINK', 'LICH TRINH', 'FILE', 'WORD', 'PDF')) {
            return 'chuong_trinh';
        }

        return null;
    }

    /**
     * Dòng chỉ có MỘT nội dung trải ngang nhiều cột (ô gộp) → dòng ngữ cảnh.
     * Trả về chữ của dòng đó.
     */
    private function laDongNguCanh(array $o, array $cot): ?string
    {
        $chu = array_values(array_unique(array_map(fn ($x) => $x['chu'], $o)));
        if (count($chu) !== 1) {
            return null;
        }
        $mau = reset($o);
        $trai = count($o) >= 3 || ($mau['rong'] ?? 1) >= 3;
        $oNgay = $this->o($o, $cot['ngay_di']);
        if (! $trai && $oNgay && $this->tachNgay($oNgay)) {
            return null; // một ô ngày đứng riêng thì vẫn là dòng dữ liệu
        }
        if (! $trai && count($o) === 1 && mb_strlen($chu[0]) < 3) {
            return null;
        }

        return $trai || ! $oNgay ? $this->motDong($chu[0]) : null;
    }

    private function laTieuDeMuc(string $chu): bool
    {
        $c = $this->chuanHoa($chu);

        return (bool) preg_match('/^(TOUR (MUA|TET|HE|THU|DONG|XUAN|LE|NOEL)|THANG \d|T\d+ ?\/|MUA |TET |LE )/', $c)
            || (bool) preg_match('/^THANG \d{1,2}\/\d{2,4}$/', $c);
    }

    // ------------------------------------------------------------------
    // Thông tin một dòng dữ liệu
    // ------------------------------------------------------------------

    private function thongTinDong(array $o, array $cot, ?string $muc, ?string $tenNguCanh): array
    {
        $oTen = $this->o($o, $cot['ten'] ?? []);
        [$ten, $chiTiet] = $oTen ? $this->tachTen($oTen['chu']) : [null, null];
        $ten ??= $tenNguCanh;
        $hang = $this->chu($o, $cot['hang_bay'] ?? []) ?: $this->timHangBay($chiTiet);
        $linkChu = null;
        $link = null;
        foreach ($cot['chuong_trinh'] ?? [] as $c) {
            if (isset($o[$c])) {
                $link ??= $o[$c]['link'];
                $linkChu ??= $o[$c]['chu'];
            }
        }
        if (! $ten) {
            // Không có cột tên, cũng không có dòng tên: lấy tên mục + nhãn link
            // (VD M Tour "TOUR MÙA ĐÔNG 2026" + "SEOUL 22/12").
            // Bỏ ngày trong nhãn ("SEOUL 22/12") để các ngày đi gộp về một tour.
            $nhan = $linkChu && ! $this->laNhanLink($linkChu)
                ? trim(preg_replace('/\d{1,2}\/\d{1,2}(\/\d{2,4})?/', '', $this->motDong($linkChu)), ' -–')
                : '';
            $ten = trim(($muc ?? '').($nhan !== '' ? ' – '.$nhan : ''), ' –') ?: 'Tour';
        }

        $chuGhiChu = $this->chu($o, $cot['ghi_chu'] ?? []);
        $chuCon = $this->chu($o, $cot['con_cho'] ?? []);
        $tong = $this->soNguyen($this->o($o, $cot['tong_cho'] ?? []));
        $chot = $this->soNguyen($this->o($o, $cot['da_chot'] ?? []));
        $giu = $this->soNguyen($this->o($o, $cot['dang_giu'] ?? []));
        $coCotCho = isset($cot['con_cho']) || isset($cot['tong_cho']);

        $con = $this->soChoCon($this->o($o, $cot['con_cho'] ?? []));
        if ($con === null && $tong !== null && ($chot !== null || $giu !== null)) {
            $con = max(0, $tong - (int) $chot - (int) $giu);
        }

        $oGia = $this->o($o, $cot['gia'] ?? []);

        return [
            'ten_tour' => $this->catDai($ten, 250),
            'chi_tiet' => $chiTiet ? $this->catDai($chiTiet, 1000) : null,
            'thoi_gian' => $this->chu($o, $cot['thoi_gian'] ?? []) ?: $this->timThoiGian(($oTen['chu'] ?? '')."\n".$ten),
            'hang_bay' => $hang ? $this->catDai($hang, 500) : null,
            'gia' => $this->tien($oGia),
            'gia_goc' => $oGia ? $this->catDai($oGia['chu'], 120) : null,
            'gia_tre_em' => $this->tien($this->o($o, $cot['gia_tre_em'] ?? [])),
            'gia_em_be' => $this->tien($this->o($o, $cot['gia_em_be'] ?? [])),
            'hoa_hong' => $this->tien($this->o($o, $cot['hoa_hong'] ?? [])),
            'tong_cho' => $tong,
            'da_chot' => $chot,
            'dang_giu' => $giu,
            'con_cho' => $con,
            'trang_thai' => $this->trangThai($con, $coCotCho, $chuGhiChu, $chuCon),
            'ghi_chu' => $chuGhiChu ? $this->catDai($chuGhiChu, 500) : null,
            'link_chuong_trinh' => $link,
            'han_visa' => $this->chu($o, $cot['han_visa'] ?? []) ?: null,
        ];
    }

    /**
     * Ô tên kiểu V1: "SEOUL - NAMI | 5N4D⏎(Hái trái cây)⏎Vietjet Air⏎VJ862 ..."
     * → tên = các dòng đầu cho tới khi gặp dòng chú thích / hãng bay / số hiệu
     * chuyến; phần còn lại là chi tiết.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function tachTen(string $chu): array
    {
        $dong = array_values(array_filter(array_map('trim', explode("\n", $chu)), fn ($d) => $d !== ''));
        $ten = [];
        foreach ($dong as $i => $d) {
            if ($i > 0 && $this->laDongChiTiet($d)) {
                break;
            }
            $ten[] = $d;
        }
        $phanTen = trim(implode(' – ', $ten));
        $phanTen = preg_replace('/^\d+\s*[\.\)]\s*(?=\D)/u', '', $phanTen); // "9. THƯỢNG HẢI" → "THƯỢNG HẢI"

        return [$phanTen ?: null, count($dong) > count($ten) ? implode("\n", $dong) : null];
    }

    private function laDongChiTiet(string $d): bool
    {
        $c = $this->chuanHoa($d);

        return str_starts_with($d, '(')
            || (bool) preg_match('/^\d+\s*(N|NGAY)\s*\d*\s*(D|DEM)?\b/', $c)
            || (bool) preg_match('/\b(AIR|AIRLINES|AIRWAYS|VIETJET|BAMBOO|CHUYEN DI|CHUYEN VE|NGAY KHOI HANH|CO MAT|KS \d|KHACH SAN)\b/', $c)
            || (bool) preg_match('/\b[A-Z0-9]{2}\s?\d{3,4}\b/', $d);
    }

    private function timHangBay(?string $chiTiet): ?string
    {
        if (! $chiTiet) {
            return null;
        }
        $dong = array_values(array_filter(array_map('trim', explode("\n", $chiTiet)), fn ($d) => preg_match('/\b(Air|Airlines|Airways|VIETJET|Vietjet|Bamboo)\b|\b[A-Z0-9]{2}\s?\d{3,4}\b/u', $d)));

        return $dong ? implode("\n", array_slice($dong, 0, 3)) : null;
    }

    private function timThoiGian(string $chu): ?string
    {
        $c = $this->chuanHoa($chu);
        if (preg_match('/\b(\d+)\s*N\s*(\d+)\s*D\b/', $c, $m) || preg_match('/\b(\d+)\s*NGAY\s*(\d+)\s*DEM\b/', $c, $m)) {
            return "{$m[1]}N{$m[2]}Đ";
        }

        return null;
    }

    private function laNhanLink(string $chu): bool
    {
        return (bool) preg_match('/^(LINK|TAI VE|WORD|PDF|DRIVE|XEM)/', $this->chuanHoa($chu));
    }

    private function trangThai(?int $con, bool $coCotCho, ?string $ghiChu, ?string $chuCon): string
    {
        $c = $this->chuanHoa(($ghiChu ?? '').' '.($chuCon ?? ''));
        if (preg_match('/\b(HUY DOAN|HUY TOUR|CANCEL|HUY)\b/', $c)) {
            return 'huy';
        }
        if (preg_match('/\b(TAM NGUNG|NGUNG NHAN|DUNG NHAN|STOP)\b/', $c)) {
            return 'tam_ngung';
        }
        if (preg_match('/\b(FULL|HET CHO)\b/', $c)) {
            return 'het_cho';
        }
        if ($con === null) {
            return 'lien_he';
        }

        return $con > 0 ? 'con_cho' : 'het_cho';
    }

    // ------------------------------------------------------------------
    // Ngày tháng
    // ------------------------------------------------------------------

    /**
     * Các ngày khởi hành trong một ô, đọc theo CHỮ HIỂN THỊ (ngày/tháng kiểu
     * Việt Nam). Mỗi dòng một ngày; dòng dạng khoảng "09/10 – 13/10" lấy ngày
     * đầu. Nếu ô có dòng khoảng thì bỏ các dòng chỉ có 1 ngày lẻ (VD "Lễ 02/09"
     * là nhãn ngày lễ, không phải ngày khởi hành).
     *
     * Không tin giá trị ngày Excel lưu bên dưới: sheet AZ thật có ô hiện
     * "19/10" nhưng lưu năm 2025 (chép dòng từ năm ngoái), và ô hiện "11/10"
     * (11 tháng 10) nhưng lưu 10/11 do nhập sai kiểu máy Mỹ. Điều hành nhìn chữ
     * hiển thị, nên đọc theo chữ; năm chỉ lấy khi chữ có ghi năm.
     *
     * @return list<array{0:int,1:int,2:?int}> [ngày, tháng, năm|null]
     */
    private function tachNgay(array $o): array
    {
        $khoang = [];
        $le = [];
        foreach (explode("\n", $o['chu']) as $dong) {
            preg_match_all('/(?<![\d\/.])(\d{1,2})[\/.](\d{1,2})(?:[\/.](\d{4}|\d{2}))?(?![\d\/.]*\d)/', $dong, $m, PREG_SET_ORDER);
            $hop = array_values(array_filter($m, fn ($x) => (int) $x[1] >= 1 && (int) $x[1] <= 31 && (int) $x[2] >= 1 && (int) $x[2] <= 12));
            if (! $hop) {
                continue;
            }
            $dau = $hop[0];
            $nam = isset($dau[3]) && $dau[3] !== '' ? $this->nam4So($dau[3]) : null;
            if (count($hop) >= 2) {
                $cuoi = $hop[1];
                // "06/02 – 10/02/2027": năm ghi ở ngày về thì ngày đi cùng năm
                // (hoặc năm trước nếu khoảng vắt qua Tết dương lịch).
                if ($nam === null && isset($cuoi[3]) && $cuoi[3] !== '') {
                    $nam = $this->nam4So($cuoi[3]) - ((int) $dau[2] > (int) $cuoi[2] ? 1 : 0);
                }
                $khoang[] = [(int) $dau[1], (int) $dau[2], $nam];
            } else {
                $le[] = [(int) $dau[1], (int) $dau[2], $nam];
            }
        }

        if (! $khoang && ! $le && $o['ngay'] instanceof Carbon) {
            // Ô ngày hiển thị kiểu khác ("7 Oct") — lấy ngày/tháng, năm vẫn suy.
            return [[(int) $o['ngay']->day, (int) $o['ngay']->month, null]];
        }

        return $khoang ?: $le;
    }

    /**
     * Sheet hầu như không ghi năm ("7/10", "*01/01"). Chốt năm theo thứ tự:
     * năm ghi ngay trong ô → năm ở ô tháng ("T2/2027") → năm của mục / tab
     * ("TOUR TẾT 2027", "SÀI GÒN 2026") → năm của ngày cập nhật / hôm nay.
     *
     * Dòng sau của cùng một tour không được lùi về trước dòng trước quá 1 tháng
     * (lịch trong một khối luôn đi tới) — 12/2026 rồi tới "05/01" là 01/2027.
     */
    private function chotNam(array $ng, ?int $namThang, ?int $namMuc, Carbon $moc, ?Carbon $truoc): ?Carbon
    {
        [$d, $m, $nam] = $ng;
        $nam ??= $namThang;

        $tao = function (int $y) use ($d, $m): ?Carbon {
            return checkdate($m, $d, $y) ? Carbon::create($y, $m, $d)->startOfDay() : null;
        };

        if ($nam !== null) {
            return $tao($nam);
        }

        $ungVien = [];
        if ($namMuc !== null) {
            // Tên mục thường mang năm của mùa ("TOUR TẾT 2027" có cả 30/12/2026)
            foreach ([$namMuc - 1, $namMuc, $namMuc + 1] as $y) {
                if ($x = $tao($y)) {
                    $ungVien[] = $x;
                }
            }
        } else {
            foreach ([$moc->year - 1, $moc->year, $moc->year + 1] as $y) {
                if ($x = $tao($y)) {
                    $ungVien[] = $x;
                }
            }
        }
        if (! $ungVien) {
            return null;
        }

        if ($truoc) {
            $sau = array_values(array_filter($ungVien, fn (Carbon $x) => $x->gte($truoc->copy()->subDays(31))));
            if ($sau) {
                return $sau[0];
            }
        }

        if ($namMuc !== null) {
            // Gần mốc nhất trong 2 năm (năm mục, năm mục − 1)
            $hai = array_values(array_filter($ungVien, fn (Carbon $x) => $x->year <= $namMuc));
            usort($hai, fn ($a, $b) => abs($a->diffInDays($moc, false)) <=> abs($b->diffInDays($moc, false)));

            return $hai[0] ?? $ungVien[0];
        }

        // Không có năm nào: sheet thường giữ lịch cũ vài tháng trước → chọn
        // ngày sớm nhất không quá 7 tháng trước mốc.
        $mocSom = $moc->copy()->subDays(213);
        foreach ($ungVien as $x) {
            if ($x->gte($mocSom)) {
                return $x;
            }
        }

        return end($ungVien);
    }

    private function timNamTrongOThang(?string $chu): ?int
    {
        if (! $chu) {
            return null;
        }
        $c = $this->chuanHoa($chu);
        if (preg_match('/(?:THANG|T)\s*\d{1,2}\s*\/\s*(\d{4}|\d{2})\b/', $c, $m)) {
            return $this->nam4So($m[1]);
        }

        return $this->timNam($chu);
    }

    private function timNam(?string $chu): ?int
    {
        if ($chu && preg_match('/(?<!\d)(20\d{2})(?!\d)/', $chu, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private function nam4So(string $y): int
    {
        return strlen($y) === 2 ? 2000 + (int) $y : (int) $y;
    }

    private function timNgayCapNhat(array $o): ?Carbon
    {
        $cacO = array_values($o);
        foreach ($cacO as $i => $x) {
            if (! str_contains($this->chuanHoa($x['chu']), 'CAP NHAT')) {
                continue;
            }
            foreach (array_slice($cacO, $i) as $sau) {
                if ($sau['ngay']) {
                    return $sau['ngay'];
                }
                if (preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{4})/', $sau['chu'], $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
                    return Carbon::create((int) $m[3], (int) $m[2], (int) $m[1])->startOfDay();
                }
            }
        }

        return null;
    }

    private function dongCoNgay(array $o): bool
    {
        foreach ($o as $x) {
            if ($x['ngay'] || preg_match('/\d{1,2}\/\d{1,2}/', $x['chu'])) {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------
    // Số, tiền
    // ------------------------------------------------------------------

    /**
     * Tiền theo chữ hiển thị trong sheet. Sheet ghi theo nghìn ("21.990",
     * COM "1.000", "500") thì nhân 1.000 — giá tour không ai ghi dưới 100.000đ.
     * "Giá cũ: 17990⏎Giá mới: 16990" → lấy giá mới. "Chờ cập nhật" → null.
     */
    private function tien(?array $o): ?int
    {
        if (! $o) {
            return null;
        }
        $chu = $o['chu'];
        if (preg_match('/m[ớo]i\s*:?\s*([\d.,\s]+)/iu', $chu, $m)) {
            $chu = $m[1];
        }
        if (! preg_match('/\d[\d.,]*/', $chu, $m)) {
            return null;
        }
        $so = (int) preg_replace('/\D/', '', $m[0]);
        if ($so <= 0) {
            return null;
        }

        return $so < 100000 ? $so * 1000 : $so;
    }

    private function soNguyen(?array $o): ?int
    {
        if (! $o) {
            return null;
        }
        if ($o['so'] !== null) {
            return (int) round($o['so']);
        }

        return preg_match('/^\s*(\d+)\s*$/', $o['chu'], $m) ? (int) $m[1] : null;
    }

    /** Số chỗ còn: "-" / "FULL" = 0, "(2)" (âm, quá chỗ) = 0, trống = không rõ. */
    private function soChoCon(?array $o): ?int
    {
        if (! $o) {
            return null;
        }
        if ($o['so'] !== null) {
            return max(0, (int) round($o['so']));
        }
        $c = trim($o['chu']);
        if ($c === '-' || $c === '–' || preg_match('/^\(\s*\d+\s*\)$/', $c) || preg_match('/\b(FULL|HET)\b/', $this->chuanHoa($c))) {
            return 0;
        }

        return preg_match('/^(\d+)/', $c, $m) ? (int) $m[1] : null;
    }

    // ------------------------------------------------------------------
    // Tiện ích
    // ------------------------------------------------------------------

    /** Ô đầu tiên có dữ liệu trong các cột cho trước. */
    private function o(array $hang, array $cacCot): ?array
    {
        foreach ($cacCot as $c) {
            if (isset($hang[$c])) {
                return $hang[$c];
            }
        }

        return null;
    }

    private function chu(array $hang, array $cacCot): ?string
    {
        return $this->o($hang, $cacCot)['chu'] ?? null;
    }

    /** Bỏ dấu, in hoa, gộp khoảng trắng — để so khớp từ khoá. */
    private function chuanHoa(string $s): string
    {
        $s = str_replace(['đ', 'Đ'], 'D', $s);

        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Z0-9\/ ]/', ' ', strtoupper(Str::ascii($s)))));
    }

    private function motDong(string $s): string
    {
        return trim(preg_replace('/\s+/u', ' ', $s));
    }

    private function catDai(string $s, int $max): string
    {
        return mb_strlen($s) > $max ? mb_substr($s, 0, $max - 1).'…' : $s;
    }

    /**
     * Khoá nhận diện "cùng một tour" giữa các lần đọc: tên đã chuẩn hoá + số
     * hiệu chuyến bay đầu tiên / hãng bay. Không gồm tên tab — V1 có tab
     * "Tour Tết" lặp lại tour của các tab nước, gộp lại là đúng.
     */
    public function khoaTour(string $ten, ?string $hang): string
    {
        $chuyen = null;
        if ($hang && preg_match('/\b([A-Z0-9]{2})\s?(\d{3,4})\b/', $hang, $m)) {
            $chuyen = $m[1].$m[2];
        } elseif ($hang) {
            $chuyen = $this->chuanHoa(strtok($hang, "\n"));
        }

        return substr(sha1($this->chuanHoa($ten).'|'.$chuyen), 0, 20);
    }

    /**
     * Cùng tour + cùng ngày xuất hiện nhiều lần (tab Tết lặp tab nước, ô ngày
     * gộp dọc) → giữ một. Số chỗ khác nhau thì lấy số NHỎ hơn cho an toàn.
     */
    private function boTrung(array $dong): array
    {
        $theoKhoa = [];
        foreach ($dong as $d) {
            $k = $d['khoa_tour'].'|'.$d['ngay_di'];
            if (! isset($theoKhoa[$k])) {
                $theoKhoa[$k] = $d;
                continue;
            }
            $cu = $theoKhoa[$k];
            if ($d['con_cho'] !== null && ($cu['con_cho'] === null || $d['con_cho'] < $cu['con_cho'])) {
                $d['tab'] = $cu['tab'];
                $theoKhoa[$k] = array_merge($cu, array_filter($d, fn ($v) => $v !== null));
            }
        }

        return array_values($theoKhoa);
    }
}
