<?php

namespace Modules\Alliance\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Normalizer;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Date as NgayExcel;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Đọc file sheet chỗ trống của đối tác liên minh → danh sách ngày khởi hành.
 *
 * Mỗi đối tác tự trình bày sheet theo kiểu riêng và PSV không sửa được, nên
 * bộ đọc KHÔNG dựa vào vị trí cột cố định mà tự nhận diện theo chữ ở dòng tiêu
 * đề ("NGÀY KHỞI HÀNH", "GIÁ", "NHẬN", "SIZE"...). Viết dựa trên 10 sheet thật
 * (V1, VNA, AZ, M Tour, J Travel HCM/HN, Hanvina, VGI, VVT, Triều Hảo):
 *
 *  - Ngày ghi đủ kiểu: "08/04", "11.01", "Tháng 10: 15, 22, 29", cột tháng
 *    + cột ngày riêng ("Tháng 1" | "21"), "06 - 10/02/2027", nhiều ngày trong
 *    một ô, "THỨ 5" (hằng tuần).
 *  - Số chỗ: SIZE/SURE/HOLD/NHẬN, "SỐ CHỖ CÒN NHẬN", "FULL", "ĐÓNG", "-",
 *    hoặc tô ĐỎ ngày hết chỗ (chỉ khi điều hành bật cho sheet đó).
 *  - Tiền: "21.990" (nghìn), "16.990K", "2tr5", "Giá cũ/Giá mới", giá KM.
 *
 * Mọi suy đoán (năm, đơn vị giá, trạng thái) đều ghi lại chữ gốc trong sheet
 * để điều hành đối chiếu được khi cần; ô ngày không hiểu được ghi vào báo cáo.
 */
class DocBangLienMinh
{
    /** Giới hạn để một file lạ không làm treo máy chủ. */
    private const SO_DONG_TOI_DA = 3000;

    private const SO_COT_TOI_DA = 40;

    /** Mỗi tab ghi tối đa chừng này ô ngày "không hiểu" vào báo cáo. */
    private const SO_KHONG_HIEU_TOI_DA = 15;

    /** @var list<string> cảnh báo trong lúc đọc */
    private array $canhBao = [];

    /** @var array<string, array<string,mixed>> báo cáo theo từng tab */
    private array $baoCao = [];

    private Carbon $homNay;

    /** Tab đang đọc — để xem màu ô khi cần (chỉ đọc màu lúc thật sự dùng). */
    private ?Worksheet $tabHienTai = null;

    /**
     * @param  array{mau_do?: string, tab_bo_qua?: list<string>, cot_tay?: array<string, array<string,string>>}  $tuyChon
     *                                                                                                                       - mau_do: 'tat' | 'theo_chu_thich' (tab có ghi "đỏ hết chỗ") | 'tat_ca'
     *                                                                                                                       - tab_bo_qua: tên các tab không đọc
     *                                                                                                                       - cot_tay: tab => [vai trò => chữ cột], cho tab không có dòng tiêu đề
     * @return array{dong: list<array<string,mixed>>, canh_bao: list<string>, ngay_cap_nhat: ?string, bao_cao: list<array<string,mixed>>}
     */
    public function doc(string $duongDan, ?Carbon $homNay = null, array $tuyChon = []): array
    {
        $this->homNay = ($homNay ?? now())->copy()->startOfDay();
        $this->canhBao = [];
        $this->baoCao = [];

        $trinhDoc = IOFactory::createReaderForFile($duongDan);
        // Cần định dạng ô (màu chữ, ô ngày tháng) và link → không bật readDataOnly.
        $trinhDoc->setReadEmptyCells(false);
        // Chỉ nạp vùng cần đọc: sheet thật có tab rộng tới cột AA, dài 1000 dòng
        // định dạng trống — nạp hết thì tốn bộ nhớ vô ích.
        $trinhDoc->setReadFilter(new class(self::SO_DONG_TOI_DA, self::SO_COT_TOI_DA) implements IReadFilter
        {
            public function __construct(private int $dong, private int $cot) {}

            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row <= $this->dong && Coordinate::columnIndexFromString($columnAddress) <= $this->cot;
            }
        });
        $sach = $trinhDoc->load($duongDan);

        $boQua = array_map(fn ($t) => $this->chuanHoa($t), $tuyChon['tab_bo_qua'] ?? []);
        $cotTay = [];
        foreach ($tuyChon['cot_tay'] ?? [] as $tab => $banDo) {
            $cotTay[$this->chuanHoa($tab)] = $banDo;
        }

        $tatCa = [];
        $ngayCapNhatChung = null;
        foreach ($sach->getWorksheetIterator() as $tab) {
            $ten = trim($tab->getTitle());
            if ($tab->getSheetState() !== Worksheet::SHEETSTATE_VISIBLE) {
                $this->baoCao[] = ['tab' => $ten, 'tinh_trang' => 'an', 'so_ngay' => 0];

                continue;
            }
            if (in_array($this->chuanHoa($ten), $boQua, true)) {
                $this->baoCao[] = ['tab' => $ten, 'tinh_trang' => 'bo_qua', 'so_ngay' => 0];

                continue;
            }
            [$dong, $capNhat] = $this->docTab($tab, $tuyChon['mau_do'] ?? 'tat', $cotTay[$this->chuanHoa($ten)] ?? null);
            $ngayCapNhatChung ??= $capNhat;
            array_push($tatCa, ...$dong);
        }
        $sach->disconnectWorksheets();
        $this->tabHienTai = null;

        return [
            'dong' => $this->boTrung($tatCa),
            'canh_bao' => $this->canhBao,
            'ngay_cap_nhat' => $ngayCapNhatChung?->toDateString(),
            'bao_cao' => $this->baoCao,
        ];
    }

    // ------------------------------------------------------------------
    // Đọc từng tab
    // ------------------------------------------------------------------

    /** @return array{0: list<array<string,mixed>>, 1: ?Carbon} */
    private function docTab(Worksheet $tab, string $mauDo, ?array $banDoTay): array
    {
        $this->tabHienTai = $tab;
        $luoi = $this->dungLuoi($tab);
        $tenTab = trim($tab->getTitle());
        $namTab = $this->timNam($tenTab);
        $doLaHet = $mauDo === 'tat_ca' || ($mauDo === 'theo_chu_thich' && $this->coChuThichDo($luoi));

        $ketQua = [];
        $cot = $banDoTay ? $this->cotTuBanDo($banDoTay) : null; // vai trò => [chỉ số cột]
        $ngayCapNhat = null;
        $muc = null;           // "THÁNG 10/2026", "TOUR TẾT 2027", "HÀN QUỐC"...
        $namMuc = null;
        $tenNguCanh = null;    // tên tour lấy từ dòng gộp (kiểu M Tour)
        $choSuyNam = [];       // khoá tour => các ngày (chưa chốt năm), theo thứ tự trong sheet
        $linkTheoTour = [];    // link chương trình chỉ ghi ở dòng đầu khối tour (V1)
        $thangTheoTour = [];   // tháng của dòng trước (cột tháng để trống / "Lễ 2/9")
        $khongHieu = [];
        $coBang = (bool) $cot;

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

            // Dòng tiêu đề (có thể 2 tầng) → xác định lại vai trò từng cột.
            // Tab khai cột bằng tay thì không dò tiêu đề.
            if (! $banDoTay && ($moi = $this->nhanDienTieuDe($luoi, $r))) {
                [$cot, $soDongTieuDe] = $moi;
                $coBang = true;
                $r += $soDongTieuDe - 1;
                $muc = null;
                $namMuc = null;
                $tenNguCanh = null;

                continue;
            }
            if (! $cot) {
                // Dòng tựa phía trên bảng: "LKH CHÂU ÂU ... - Năm 2026", "LỊCH KHỞI
                // HÀNH TOUR 2026" → năm của cả tab (VVT ghi "Tháng 1 | 21" nghĩa là
                // 21/01/2026 đã qua, không phải 2027).
                foreach ($o as $x) {
                    if (preg_match('/(N\p{L}M|L\p{L}CH|LKH|TOUR|TH\p{L}NG)\D{0,40}(20\d{2})/iu', $x['chu'], $mn)) {
                        $namTab = (int) $mn[2];
                        break;
                    }
                }

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

            if (! $this->dongCoODuLieuNgay($o, $cot)) {
                continue;
            }

            $thongTin = $this->thongTinDong($o, $cot, $muc, $tenNguCanh);
            $khoaTour = $this->khoaTour($thongTin['ten_tour'], $thongTin['hang_bay'], $thongTin['thoi_gian']);

            [$cacNgay, $tuan, $thangMoi] = $this->ngayCuaDong($o, $cot, $thangTheoTour[$khoaTour] ?? null);
            if ($thangMoi) {
                $thangTheoTour[$khoaTour] = $thangMoi;
            }
            if (! $cacNgay && ! $tuan) {
                $chiThang = ($x = $this->chuONgay($o, $cot)) && $this->thangTrongO($x) && ! preg_match('/\d\D+\d/', preg_replace('/^\s*\S+\s*\d{1,2}/u', '', $x));
                if (! $chiThang && count($khongHieu) < self::SO_KHONG_HIEU_TOI_DA && ($chuNgay = $this->chuONgay($o, $cot)) && preg_match('/(?<!\d)\d{1,2}(?!\d)/', $chuNgay)) {
                    $khongHieu[] = ['dong' => $r + 1, 'chu' => $this->catDai($this->motDong($chuNgay), 80)];
                }

                continue;
            }

            if ($thongTin['link_chuong_trinh']) {
                $linkTheoTour[$khoaTour] ??= $thongTin['link_chuong_trinh'];
            } else {
                $thongTin['link_chuong_trinh'] = $linkTheoTour[$khoaTour] ?? null;
            }
            $chung = $thongTin + [
                'tab' => $tenTab,
                'muc' => $muc ?? $thongTin['nhom'],
                'khoa_tour' => $khoaTour,
                'dong_sheet' => $r + 1,
                'lich_tuan' => null,
            ];

            if ($tuan) {
                $ketQua[] = ['ngay_di' => null, 'lich_tuan' => $tuan] + $chung;

                continue;
            }

            $namThang = $this->timNamTrongOThang($this->chu($o, $cot['thang'] ?? []));
            foreach ($cacNgay as [$d, $m, $y, $do, $chuDong]) {
                $choSuyNam[$khoaTour][] = [
                    'ng' => [$d, $m, $y ?? $this->namTheoTet($d, $m, $chuDong, $ngayCapNhat ?? $this->homNay)],
                    'nam_thang' => $namThang,
                    'nam_muc' => $namMuc ?? $namTab,
                    'moc' => $ngayCapNhat ?? $this->homNay,
                    'do' => $do,
                    'dong' => $chung,
                ];
            }
        }

        // Suy năm theo CẢ CHUỖI ngày của từng tour (xem giaiNam), rồi mới lọc
        // ngày đã qua.
        foreach ($choSuyNam as $cacMuc) {
            foreach ($this->giaiNam($cacMuc) as $i => $ngay) {
                // Chỉ giữ ngày đi từ hôm nay trở đi (sheet thường giữ cả lịch
                // cũ từ đầu năm), và bỏ ngày vô lý quá xa.
                if (! $ngay || $ngay->lt($this->homNay) || $ngay->gt($this->homNay->copy()->addYears(2))) {
                    continue;
                }
                $dong = ['ngay_di' => $ngay->toDateString()] + $cacMuc[$i]['dong'];
                if ($cacMuc[$i]['do'] && $doLaHet) {
                    // Đối tác tô đỏ ngày này = hết chỗ
                    $dong['con_cho'] = 0;
                    $dong['trang_thai'] = 'het_cho';
                    $dong['ghi_chu'] = trim(($dong['ghi_chu'] ? $dong['ghi_chu'].' · ' : '').'Sheet tô đỏ (hết chỗ)');
                }
                $ketQua[] = $dong;
            }
        }

        $this->baoCao[] = [
            'tab' => $tenTab,
            'tinh_trang' => $coBang ? 'da_doc' : 'khong_co_bang',
            'so_ngay' => count($ketQua),
            'cot_tay' => (bool) $banDoTay,
            'mau_do' => $doLaHet,
            'khong_hieu' => $khongHieu,
        ];
        if (! $coBang && $soDong > 0) {
            $this->canhBao[] = "Tab “{$tenTab}”: không tìm thấy dòng tiêu đề có cột ngày khởi hành.";
        }

        return [$ketQua, $ngayCapNhat];
    }

    /**
     * Lưới ô của tab: [dòng][cột] => ['chu' => chữ hiển thị, 'so' => số|null,
     * 'ngay' => Carbon|null, 'link' => url|null, 'toa' => "F12", 'do' => các
     * khoảng chữ đỏ]. Ô gộp được chép giá trị ra mọi ô con — nhờ vậy dòng nào
     * cũng "thấy" tên tour / giá của khối gộp.
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
        $do = [];
        try {
            $tho = $o->getValue();
            if ($tho instanceof RichText) {
                // Chữ nhiều màu trong một ô: "THÁNG 10: 10, 24, 31" mà 24 tô đỏ.
                // Ghi lại vị trí (byte) các đoạn đỏ để biết NGÀY NÀO bị tô.
                $chu = '';
                foreach ($tho->getRichTextElements() as $doan) {
                    $t = $this->chuanUnicode($doan->getText());
                    $mau = $doan->getFont()?->getColor()?->getRGB();
                    if ($mau && $this->laMauDo($mau) && trim($t) !== '') {
                        $do[] = [strlen($chu), strlen($chu) + strlen($t)];
                    }
                    $chu .= $t;
                }
            } elseif ($o->isFormula()) {
                // Không tự tính công thức (chậm, dễ lỗi): lấy kết quả sheet đã lưu sẵn.
                $tho = $o->getOldCalculatedValue();
                $chu = $this->chuanUnicode((string) $tho);
            } else {
                $chu = $this->chuanUnicode((string) $o->getFormattedValue());
            }
        } catch (\Throwable) {
            return null;
        }

        // Bỏ khoảng trắng đầu/cuối nhưng giữ đúng vị trí các đoạn đỏ
        $chu = str_replace("\r", ' ', $chu);
        $dau = strlen($chu) - strlen(ltrim($chu));
        $chu = trim($chu);
        if ($chu === '' || $chu === '#N/A' || $chu === '#REF!') {
            return null;
        }
        if ($dau) {
            $do = array_map(fn ($k) => [$k[0] - $dau, $k[1] - $dau], $do);
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
            'chu' => $chu,
            'so' => is_numeric($tho) ? (float) $tho : null,
            'ngay' => $ngay,
            'link' => $link && preg_match('#^https?://#i', $link) ? $link : null,
            'toa' => $o->getCoordinate(),
            'do' => $do,
        ];
    }

    /**
     * Đổi chữ "phông đặc biệt" về chữ thường: VNA ghi tên tour/giá bằng ký tự
     * toán học in đậm (𝐒𝐄𝐎𝐔𝐋, 𝟭𝟵.𝟵𝟵𝟬) — nhìn như chữ nhưng máy không đọc được.
     * NFKC còn ghép dấu tiếng Việt về một dạng thống nhất.
     */
    private function chuanUnicode(string $s): string
    {
        if ($s === '' || ! class_exists(Normalizer::class)) {
            return $s;
        }

        return Normalizer::normalize($s, Normalizer::FORM_KC) ?: $s;
    }

    /** Đỏ: FF0000, EA4335, C00000, E06666... (đỏ đậm tới đỏ nhạt, không tính cam/hồng nhạt). */
    private function laMauDo(string $rgb): bool
    {
        if (! preg_match('/^[0-9A-F]{6}$/i', $rgb)) {
            return false;
        }
        [$r, $g, $b] = array_map('hexdec', str_split($rgb, 2));

        return $r >= 0xB0 && $g <= 0x80 && $b <= 0x80 && $r - max($g, $b) >= 0x60;
    }

    /** Cả ô bị tô đỏ (màu chữ của cả ô, hoặc nền đỏ). Chỉ đọc màu khi cần. */
    private function caODo(array $o): bool
    {
        if (! $this->tabHienTai || empty($o['toa'])) {
            return false;
        }
        try {
            $kieu = $this->tabHienTai->getStyle($o['toa']);
            if ($this->laMauDo($kieu->getFont()->getColor()->getRGB())) {
                return true;
            }

            return $kieu->getFill()->getFillType() === Fill::FILL_SOLID
                && $this->laMauDo($kieu->getFill()->getStartColor()->getRGB());
        } catch (\Throwable) {
            return false;
        }
    }

    /** Tab có chú thích kiểu "Đỏ hết chỗ" / "Màu đỏ: full" (Hanvina). */
    private function coChuThichDo(array $luoi): bool
    {
        foreach ($luoi as $hang) {
            foreach ($hang as $x) {
                if (mb_strlen($x['chu']) <= 60 && preg_match('/\bDO\b(\s+\w+){0,2}\s+(HET CHO|FULL)\b/', $this->chuanHoa($x['chu']))) {
                    return true;
                }
            }
        }

        return false;
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
        if (count($this->vaiTroCuaDong($tren)) < 3 || $this->dongCoNgay($tren) || $this->coGiaTien($tren)) {
            return null;
        }

        // Tiêu đề 2 tầng: "LỊCH KHỞI HÀNH" ở trên, "THÁNG | NGÀY" ở dưới
        // (dòng dữ liệu đầu bảng có thể chứa chữ giống tiêu đề — "CHƯƠNG TRÌNH",
        // "TOUR GHÉP" — nhưng luôn có giá tiền, tiêu đề thì không)
        $duoi = $luoi[$r + 1] ?? [];
        $haiTang = $duoi
            && count($this->vaiTroCuaDong($duoi)) >= 2
            && ! $this->dongCoNgay($duoi)
            && ! $this->coGiaTien(array_filter($duoi, fn ($x, $c) => ($tren[$c]['chu'] ?? null) !== $x['chu'], ARRAY_FILTER_USE_BOTH));

        $nhan = [];
        foreach (array_unique(array_merge(array_keys($tren), $haiTang ? array_keys($duoi) : [])) as $c) {
            $a = $tren[$c]['chu'] ?? '';
            $b = $haiTang ? ($duoi[$c]['chu'] ?? '') : '';
            $nhan[$c] = [$this->chuanHoa($a), $this->chuanHoa($b === $a ? '' : $b)];
        }
        ksort($nhan);

        // Mỗi vai trò lấy cột có độ ưu tiên cao nhất (số nhỏ), bằng nhau thì
        // cột trái. Riêng link chương trình và ngày đi giữ nhiều cột: "LỊCH
        // KHỞI HÀNH" gộp 2 cột = cột tháng + cột ngày (VVT, Triều Hảo).
        $cot = [];
        $uuTien = [];
        $tenThua = []; // cột "CHƯƠNG TRÌNH"/"LỊCH TRÌNH" thua cột tên tốt hơn
        foreach ($nhan as $c => [$a, $b]) {
            $vt = $this->vaiTroCot($a, $b);
            if (! $vt) {
                continue;
            }
            [$vaiTro, $muc] = $vt;
            if (in_array($vaiTro, ['chuong_trinh', 'ngay_di'], true)) {
                $cot[$vaiTro][] = $c;

                continue;
            }
            if ($vaiTro === 'ten' && $muc >= 2) {
                $tenThua[$c] = $muc;
            }
            if (! isset($uuTien[$vaiTro]) || $muc < $uuTien[$vaiTro]) {
                $cot[$vaiTro] = [$c];
                $uuTien[$vaiTro] = $muc;
            }
        }
        // Đã có cột tên tốt hơn ("CUNG ĐƯỜNG") thì cột "CHƯƠNG TRÌNH" là cột
        // link tải chương trình (AZ ghi "Link tải" ở đó).
        foreach (array_keys($tenThua) as $c) {
            if (! in_array($c, $cot['ten'] ?? [], true)) {
                $cot['chuong_trinh'][] = $c;
            }
        }

        // Không có cột tên nào khác thì "TUYẾN" / "TOUR" chính là tên tour (VNA)
        if (! isset($cot['ten']) && isset($cot['nhom'])) {
            $cot['ten'] = $cot['nhom'];
            unset($cot['nhom']);
        }
        // Không có cột ngày, chỉ có cột THÁNG ghi "Tháng 11: 11" (J Travel)
        if (! isset($cot['ngay_di']) && isset($cot['thang'])) {
            $cot['ngay_di'] = $cot['thang'];
            unset($cot['thang']);
        }
        if (! isset($cot['ngay_di'])) {
            return null;
        }
        // "LỊCH KHỞI HÀNH" chỉ ghi ở 1 ô nhưng bên dưới chia 2 cột "Tháng 6" |
        // "17" (Triều Hảo): cột ngay bên phải không có tiêu đề → là cột ngày.
        $phai = max($cot['ngay_di']) + 1;
        if (! isset($nhan[$phai]) || $nhan[$phai] === ['', '']) {
            $cot['ngay_di'][] = $phai;
        }
        $cot['_tieu_de'] = array_keys($nhan);

        return [$cot, $haiTang ? 2 : 1];
    }

    /**
     * Dòng có số kiểu giá tiền / số lượng lớn ("17,990", "135") → là dòng dữ
     * liệu, không phải tiêu đề. Năm (2026) trong tiêu đề thì không tính.
     */
    private function coGiaTien(array $o): bool
    {
        foreach ($o as $x) {
            if (preg_match('/\d{3,}|\d[.,]\d{3}/', preg_replace('/(?<!\d)20\d{2}(?!\d)/', '', $x['chu']))) {
                return true;
            }
        }

        return false;
    }

    private function vaiTroCuaDong(array $o): array
    {
        $vt = [];
        foreach ($o as $x) {
            if (mb_strlen($x['chu']) > 30 || $x['ngay']) {
                continue;
            }
            if ($v = $this->vaiTroCot($this->chuanHoa($x['chu']), '')) {
                $vt[$v[0]] = true;
            }
        }

        return array_keys($vt);
    }

    /**
     * Vai trò của cột theo nhãn tầng trên ($a) và tầng dưới ($b), đã chuẩn hoá.
     *
     * @return array{0: string, 1: int}|null [vai trò, độ ưu tiên — số nhỏ thắng]
     */
    private function vaiTroCot(string $a, string $b): ?array
    {
        $du = trim("$a $b");
        $cuoi = $b !== '' ? $b : $a;
        $co = fn (string $s, string ...$tu) => (bool) array_filter($tu, fn ($t) => preg_match('/(^| )'.preg_quote($t, '/').'( |$)/', $s));

        if ($du === '' || $co($du, 'CAP NHAT')) {
            return null;
        }
        if ($co($du, 'DEADLINE', 'DEALINE', 'HAN VISA', 'HAN NOP')) {
            return ['han_visa', 1];
        }
        if (in_array($cuoi, ['STT', 'TT', 'LN', 'LOI NHUAN', 'AG', 'THT'], true)) {
            return null; // LN = lợi nhuận nội bộ đối tác — không hiện
        }
        if ($co($du, 'MA TOUR', 'MA LICH', 'CODE', 'MA CT')) {
            return ['ma_tour', 1];
        }
        // Link trước: "CHƯƠNG TRÌNH CHI TIẾT", "LINK CHƯƠNG TRÌNH", "LINK CT"...
        if ($co($du, 'LINK', 'CHI TIET', 'WORD', 'PDF', 'FILE', 'DRIVE', 'NLG', 'JTV', 'LOGO', 'TAI VE')) {
            return ['chuong_trinh', 1];
        }
        // Hoa hồng: COM AG (cho đại lý) > COM / HH > TỔNG COM; HH trẻ em bỏ qua
        if ($co($du, 'COM', 'HH', 'HOA HONG')) {
            if ($co($du, 'CHD', 'TRE EM')) {
                return null;
            }
            if ($co($du, 'COM AG')) {
                return ['hoa_hong', 1];
            }

            return ['hoa_hong', $co($du, 'TONG COM') ? 3 : 2];
        }
        if ($co($du, 'TRE EM')) {
            return ['gia_tre_em', 1];
        }
        if ($co($du, 'EM BE')) {
            return ['gia_em_be', 1];
        }
        if ($co($cuoi, 'NHAN', 'CON CHO', 'CON LAI', 'CHO TRONG', 'CON NHAN')) {
            return ['con_cho', 1];
        }
        if ($co($cuoi, 'SURE', 'DA CHOT', 'CHOT', 'DA BAN', 'DA DAT')) {
            return ['da_chot', 1];
        }
        if ($co($cuoi, 'HOLD', 'GIU CHO', 'DANG GIU', 'GIU')) {
            return ['dang_giu', 1];
        }
        if ($co($cuoi, 'SIZE', 'TONG CHO', 'SO CHO', 'SL KHACH')) {
            return ['tong_cho', 1];
        }
        if ($cuoi === 'SL' || $co($cuoi, 'TINH TRANG')) {
            return ['tinh_trang', 1]; // Hanvina ghi "Full" / "HỦY" ở cột SL
        }
        if ($co($cuoi, 'THOI GIAN', 'SO NGAY', 'TG', 'TIME')) {
            return ['thoi_gian', 1];
        }
        if ($co($cuoi, 'NGAY VE', 'NGAY KET THUC')) {
            return null;
        }
        // Ngày / tháng: quyết theo tầng dưới ("THÁNG" | "NGÀY") trước
        // Chỉ chữ "THÁNG" đứng riêng; "Tháng 10" là dữ liệu chứ không phải tiêu đề
        if (preg_match('/^THANG( KHOI HANH| DI)?$/', $cuoi)) {
            return ['thang', 1];
        }
        if ($co($cuoi, 'NGAY', 'NGAY KHOI HANH', 'NGAY DI', 'KHOI HANH', 'LICH KHOI HANH', 'LICH KH', 'KH', 'NGAY KH')) {
            return ['ngay_di', 1];
        }
        if ($co($du, 'GIA KHUYEN MAI', 'GIA KM', 'GIA DIEU CHINH', 'GIA UU DAI', 'GIA SALE')) {
            return ['gia_km', 1];
        }
        if ($co($du, 'GIA BAN', 'GIA TOUR', 'NGUOI LON', 'GIA NET', 'GIA TRON GOI', 'GIA NGUOI LON')) {
            return ['gia', 1];
        }
        if ($co($du, 'GIA')) {
            return ['gia', 2];
        }
        if ($co($cuoi, 'TEN', 'TEN TOUR', 'CUNG DUONG', 'HANH TRINH', 'TUYEN DU LICH', 'SAN PHAM', 'TEN CHUONG TRINH')) {
            return ['ten', 1];
        }
        if ($co($cuoi, 'CHUONG TRINH')) {
            return ['ten', 2]; // VGI, Hanvina: cột "CHƯƠNG TRÌNH" là tên tour
        }
        if ($co($cuoi, 'LICH TRINH')) {
            return ['ten', 3];
        }
        if ($co($cuoi, 'THI TRUONG', 'TOUR', 'TUYEN', 'QUOC GIA')) {
            return ['nhom', 1]; // J Travel "THỊ TRƯỜNG", VGI "TUYẾN": tên nhóm
        }
        if ($co($cuoi, 'HK', 'HANG KHONG', 'HANG BAY', 'CHUYEN BAY', 'HANG', 'PHUONG TIEN')) {
            return ['hang_bay', 1];
        }
        if ($co($cuoi, 'NOTE', 'GHI CHU', 'LUU Y')) {
            return ['ghi_chu', 1];
        }

        return null;
    }

    /** Bản đồ cột khai tay ("F") → chỉ số cột (5). */
    private function cotTuBanDo(array $banDo): ?array
    {
        $cot = [];
        foreach ($banDo as $vaiTro => $chuCot) {
            $chuCot = strtoupper(trim((string) $chuCot));
            if ($chuCot !== '' && preg_match('/^[A-Z]{1,2}$/', $chuCot)) {
                $cot[$vaiTro] = [Coordinate::columnIndexFromString($chuCot) - 1];
            }
        }
        if (! isset($cot['ngay_di'])) {
            return null;
        }
        $cot['_tieu_de'] = array_merge(...array_values($cot));

        return $cot;
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
        if (! $trai && $oNgay && preg_match('/\d/', $oNgay['chu'])) {
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
        $nhom = ($x = $this->chu($o, $cot['nhom'] ?? [])) ? $this->motDong(strtok($x, "\n")) : null;
        if (! $ten) {
            // Không có cột tên, cũng không có dòng tên: lấy tên mục + nhãn link
            // (VD M Tour "TOUR MÙA ĐÔNG 2026" + "SEOUL 22/12").
            // Bỏ ngày trong nhãn ("SEOUL 22/12") để các ngày đi gộp về một tour.
            $nhan = $linkChu && ! $this->laNhanLink($linkChu)
                ? trim(preg_replace('/\d{1,2}\/\d{1,2}(\/\d{2,4})?/', '', $this->motDong($linkChu)), ' -–')
                : '';
            $ten = trim(($muc ?? $nhom ?? '').($nhan !== '' ? ' – '.$nhan : ''), ' –') ?: 'Tour';
        }

        $chuGhiChu = $this->chu($o, $cot['ghi_chu'] ?? []);
        $chuCon = $this->chu($o, $cot['con_cho'] ?? []);
        $tong = $this->soNguyen($this->o($o, $cot['tong_cho'] ?? []));
        $chot = $this->soNguyen($this->o($o, $cot['da_chot'] ?? []));
        $giu = $this->soNguyen($this->o($o, $cot['dang_giu'] ?? []));

        $con = $this->soChoCon($this->o($o, $cot['con_cho'] ?? []));
        if ($con === null && $tong !== null && ($chot !== null || $giu !== null)) {
            $con = max(0, $tong - (int) $chot - (int) $giu);
        }

        // Ô tình trạng ghi đúng một chữ ("Full", "HỦY", "ĐÓNG ĐOÀN") — ở cột SL
        // hoặc một cột không có tiêu đề (Hanvina HCM, Triều Hảo Nhật Bản).
        $chuTinhTrang = [$this->chu($o, $cot['tinh_trang'] ?? [])];
        $daDung = array_merge(...array_values(array_diff_key($cot, ['_tieu_de' => 1])));
        foreach ($o as $c => $x) {
            if (! in_array($c, $daDung, true)) {
                $chuTinhTrang[] = $x['chu'];
            }
        }

        $oGia = $this->o($o, $cot['gia'] ?? []);
        $gia = $this->tien($oGia);
        $oKm = $this->o($o, $cot['gia_km'] ?? []);
        $giaKm = $this->tien($oKm);
        $giaNiemYet = null;
        // Giá KM hợp lệ phải thấp hơn giá gốc và không thấp vô lý (< 30%).
        // Đối tác hay ghi chữ vào cột này ("FULL", "Nhận 2 pax có visa") →
        // coi là ghi chú / tình trạng.
        if ($giaKm && (! $gia || ($giaKm < $gia && $giaKm >= 0.3 * $gia))) {
            // Có giá khuyến mãi: hiện giá KM làm giá chính, giá gốc gạch ngang
            $giaNiemYet = $gia;
            $gia = $giaKm;
        } elseif ($oKm && ! $giaKm) {
            $chuTinhTrang[] = $oKm['chu'];
            if (! preg_match('/^(FULL|HET CHO)$/', $this->chuanHoa($oKm['chu']))) {
                $chuGhiChu = trim(($chuGhiChu ? $chuGhiChu.' · ' : '').$oKm['chu']);
            }
        }

        return [
            'ten_tour' => $this->catDai($ten, 250),
            'chi_tiet' => $chiTiet ? $this->catDai($chiTiet, 1000) : null,
            'thoi_gian' => $this->chu($o, $cot['thoi_gian'] ?? []) ?: $this->timThoiGian(($oTen['chu'] ?? '')."\n".$ten),
            'hang_bay' => $hang ? $this->catDai($hang, 500) : null,
            'nhom' => $nhom ? $this->catDai($nhom, 200) : null,
            'ma_tour' => ($x = $this->chu($o, $cot['ma_tour'] ?? [])) ? $this->catDai($this->motDong($x), 60) : null,
            'gia' => $gia,
            'gia_niem_yet' => $giaNiemYet,
            'gia_goc' => $oGia ? $this->catDai($oGia['chu'], 120) : null,
            'gia_tre_em' => $this->tien($this->o($o, $cot['gia_tre_em'] ?? [])),
            'gia_em_be' => $this->tien($this->o($o, $cot['gia_em_be'] ?? [])),
            'hoa_hong' => $this->tien($this->o($o, $cot['hoa_hong'] ?? [])),
            'tong_cho' => $tong,
            'da_chot' => $chot,
            'dang_giu' => $giu,
            'con_cho' => $con,
            'trang_thai' => $this->trangThai($con, $chuGhiChu, $chuCon, array_filter($chuTinhTrang)),
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

    /**
     * @param  list<string>  $chuTinhTrang  ô chỉ ghi đúng một chữ tình trạng (so khớp nguyên ô)
     */
    private function trangThai(?int $con, ?string $ghiChu, ?string $chuCon, array $chuTinhTrang = []): string
    {
        // Ghi chú / ô còn chỗ: tìm từ khoá trong câu
        $c = $this->chuanHoa(($ghiChu ?? '').' '.($chuCon ?? ''));
        if (preg_match('/\b(HUY DOAN|HUY TOUR|CANCEL|HUY)\b/', $c)) {
            return 'huy';
        }
        if (preg_match('/\b(TAM NGUNG|NGUNG NHAN|DUNG NHAN|STOP)\b/', $c)) {
            return 'tam_ngung';
        }
        if (preg_match('/\b(FULL|HET CHO|DONG DOAN)\b/', $c) || $this->chuanHoa((string) $chuCon) === 'DONG') {
            return 'het_cho';
        }

        // Ô tình trạng: phải là NGUYÊN Ô đúng một chữ, để "16/01 Đóng đoàn"
        // (hạn chót) hay "Hạn visa 21/12" không bị hiểu thành hết chỗ.
        foreach ($chuTinhTrang as $x) {
            $k = $this->chuanHoa($x);
            if (preg_match('/^(HUY|HUY DOAN|HUY TOUR|CANCEL|CANCELLED)$/', $k)) {
                return 'huy';
            }
            if (preg_match('/^(TAM NGUNG|TAM NGUNG NHAN KHACH|STOP)$/', $k)) {
                return 'tam_ngung';
            }
            if (preg_match('/^(FULL|HET CHO|HET|DONG|DONG DOAN|DA DONG DOAN|SOLD OUT)$/', $k)) {
                return 'het_cho';
            }
        }

        if ($con === null) {
            return 'lien_he';
        }

        return $con > 0 ? 'con_cho' : 'het_cho';
    }

    // ------------------------------------------------------------------
    // Ngày tháng
    // ------------------------------------------------------------------

    /** Dòng có ít nhất một ô ngày / tháng có chữ (mới đáng đọc). */
    private function dongCoODuLieuNgay(array $o, array $cot): bool
    {
        foreach (array_merge($cot['ngay_di'] ?? [], $cot['thang'] ?? []) as $c) {
            if (isset($o[$c])) {
                return true;
            }
        }

        return false;
    }

    private function chuONgay(array $o, array $cot): ?string
    {
        $chu = [];
        foreach (array_unique(array_merge($cot['thang'] ?? [], $cot['ngay_di'] ?? [])) as $c) {
            if (isset($o[$c])) {
                $chu[] = $o[$c]['chu'];
            }
        }

        return $chu ? implode(' | ', array_unique($chu)) : null;
    }

    /**
     * Các ngày khởi hành của một dòng, gộp mọi kiểu ghi:
     *  1. Ô ngày có ngày/tháng hoặc "Tháng 10: 15, 22" → đọc thẳng ô đó.
     *  2. Ô ngày chỉ có số ngày ("21", "12; 27", "02(26AL)") → ghép với tháng ở
     *     cột tháng; cột tháng trống / ghi "Lễ 2/9" thì lấy tháng của dòng trên.
     *  3. Không có ngày nào mà ghi "THỨ 5" → khởi hành hằng tuần.
     *
     * @param  array{0:int,1:?int}|null  $thangTruoc  tháng (và năm) của dòng trên cùng tour
     * @return array{0: list<array{0:int,1:int,2:?int,3:bool}>, 1: ?string, 2: ?array} [ngày, lịch tuần, tháng mới]
     */
    private function ngayCuaDong(array $o, array $cot, ?array $thangTruoc): array
    {
        $cotNgay = array_values(array_filter($cot['ngay_di'] ?? [], fn ($c) => isset($o[$c])));
        $oThang = $this->o($o, $cot['thang'] ?? []);
        // "LỊCH KHỞI HÀNH" gộp 2 cột: cột đầu là tháng nếu nó ghi "Tháng ..."
        if (! $oThang && count($cotNgay) >= 2 && $this->thangTrongO($o[$cotNgay[0]]['chu'])) {
            $oThang = $o[$cotNgay[0]];
            array_shift($cotNgay);
        }
        $thang = $oThang ? $this->thangTrongO($oThang['chu']) : null;

        // 1. Đọc thẳng các ô ngày. Đã biết tháng mà ô ngày chỉ toàn số và dấu
        // chấm ("8.15.22", "18.25" — VVT) thì đó là danh sách ngày, sang bước 2.
        foreach ($cotNgay as $c) {
            if ($oThang && $o[$c]['toa'] === $oThang['toa']) {
                continue;
            }
            if (($thang ?? $thangTruoc) && preg_match('/^[\d\s.,;&]+$/', $o[$c]['chu']) && ! str_contains($o[$c]['chu'], '/')) {
                continue;
            }
            if ($ds = $this->tachNgay($o[$c])) {
                return [$ds, null, $thang];
            }
        }

        // 2. Ô ngày chỉ có số ngày → ghép tháng
        $oNgay = $cotNgay ? $o[$cotNgay[count($cotNgay) - 1]] : null;
        if ($oNgay && ($so = $this->soNgayTrongO($oNgay))) {
            $th = $thang ?? $thangTruoc;
            if ($th) {
                $doCaO = $this->caODo($oNgay);

                return [array_map(fn ($x) => [$x[0], $th[0], $th[1], $x[1] || $doCaO, count($so) === 1 ? $oNgay['chu'] : ''], $so), null, $th];
            }
        }

        // Ô tháng tự chứa "Tháng 11: 11" (cột tháng là cột ngày luôn)
        if ($oThang && ($ds = $this->tachNgay($oThang))) {
            return [$ds, null, $thang];
        }

        // 3. Hằng tuần
        foreach (array_filter([$oNgay, $oThang]) as $x) {
            if ($tuan = $this->lichTuan($x['chu'])) {
                return [[], $tuan, $thang];
            }
        }

        return [[], null, $thang];
    }

    /** "Tháng 11", "THÁNG 2/2027", "Tháng10", "T1/2027" → [tháng, năm|null]; "Tháng 11: 11" thì không (đó là ngày). */
    private function thangTrongO(string $chu): ?array
    {
        if (preg_match('/^\s*(?:th\p{L}ng|T)\s*(\d{1,2})(?:\s*\/\s*(\d{4}|\d{2}))?(?!\s*\d)(?!\s*:\s*\d)/iu', $chu, $m)
            && (int) $m[1] >= 1 && (int) $m[1] <= 12) {
            return [(int) $m[1], isset($m[2]) && $m[2] !== '' ? $this->nam4So($m[2]) : null];
        }

        return null;
    }

    /**
     * Ô chỉ có số ngày: "21", "12; 27", "02(26AL)", "25 (Giáng sinh)", "05; 19".
     * Bỏ phần trong ngoặc (ngày âm lịch, ghi chú).
     *
     * @return list<array{0:int,1:bool}> [ngày, có tô đỏ]
     */
    private function soNgayTrongO(array $o): array
    {
        $chu = $o['chu'];
        if (str_contains($chu, '/')) {
            return [];
        }
        $boNgoac = preg_replace_callback('/\([^)]*\)?/u', fn ($m) => str_repeat(' ', strlen($m[0])), $chu);
        // Ngoài ngoặc chỉ được có số, dấu phân cách và chữ "Đêm"/"Ngày" —
        // "THỨ 5" (hằng tuần) hay "MÙNG 1" không phải danh sách ngày.
        if (preg_match('/\p{L}/u', preg_replace('/(?<!\p{L})(đêm|ngày)(?!\p{L})/iu', '', $boNgoac))) {
            return [];
        }
        preg_match_all('/(?<!\d)(\d{1,2})(?!\d)/', $boNgoac, $m, PREG_OFFSET_CAPTURE);
        $kq = [];
        foreach ($m[1] as [$so, $viTri]) {
            if ((int) $so >= 1 && (int) $so <= 31) {
                $kq[] = [(int) $so, $this->trongDoanDo($o, $viTri)];
            }
        }

        return $kq;
    }

    /**
     * Các ngày khởi hành trong một ô, đọc theo CHỮ HIỂN THỊ (ngày/tháng kiểu
     * Việt Nam):
     *  - "Tháng 10: 15, 22, 29" (nhiều tháng mỗi dòng một tháng)
     *  - "09/10 – 13/10" (khoảng) → ngày đầu; "06 - 10/02/2027" → 06/02/2027
     *  - "08/04", "11.01", "LỄ 27/08", "[ĐÊM] 26/10/2026"
     * Nếu ô có dòng khoảng thì bỏ các dòng chỉ có 1 ngày lẻ (VD "Lễ 02/09"
     * là nhãn ngày lễ, không phải ngày khởi hành).
     *
     * Không tin giá trị ngày Excel lưu bên dưới: sheet AZ thật có ô hiện
     * "19/10" nhưng lưu năm 2025 (chép dòng từ năm ngoái), và ô hiện "11/10"
     * (11 tháng 10) nhưng lưu 10/11 do nhập sai kiểu máy Mỹ. Điều hành nhìn chữ
     * hiển thị, nên đọc theo chữ; năm chỉ lấy khi chữ có ghi năm.
     *
     * @return list<array{0:int,1:int,2:?int,3:bool}> [ngày, tháng, năm|null, tô đỏ]
     */
    private function tachNgay(array $o): array
    {
        $khoang = [];
        $le = [];
        $theoThang = [];
        $doCaO = null; // chỉ đọc màu cả ô khi có ngày

        foreach (preg_split('/\n/', $o['chu'], -1, PREG_SPLIT_OFFSET_CAPTURE) as [$dong, $dauDong]) {
            // "Tháng 10: 15, 22, 29" / "THÁNG 01/ 2027: 06" / "T2/2027: 05"
            if (preg_match_all('/(?:th\p{L}ng|\bT)\s*(\d{1,2})\s*(?:\/\s*(\d{4}|\d{2}))?\s*:/iu', $dong, $mt, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($mt as $i => $khop) {
                    $thang = (int) $khop[1][0];
                    if ($thang < 1 || $thang > 12) {
                        continue;
                    }
                    $nam = isset($khop[2]) && $khop[2][0] !== '' ? $this->nam4So($khop[2][0]) : null;
                    $tu = $khop[0][1] + strlen($khop[0][0]);
                    $den = isset($mt[$i + 1]) ? $mt[$i + 1][0][1] : strlen($dong);
                    $doan = substr($dong, $tu, $den - $tu);
                    // bỏ ngoặc "(TẾT DƯƠNG)", "(KM)"; giữ nguyên độ dài để khớp vị trí màu
                    $doan = preg_replace_callback('/\([^)]*\)?/u', fn ($m) => str_repeat(' ', strlen($m[0])), $doan);
                    // "Tháng 04: 24/04" — ghi đủ ngày/tháng sau dấu hai chấm
                    if (preg_match_all('/(?<![\d\/.])(\d{1,2})\/(\d{1,2})(?:\/(\d{4}|\d{2}))?/', $doan, $md, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                        foreach ($md as $x) {
                            if ((int) $x[1][0] >= 1 && (int) $x[1][0] <= 31 && (int) $x[2][0] >= 1 && (int) $x[2][0] <= 12) {
                                $theoThang[] = [(int) $x[1][0], (int) $x[2][0], isset($x[3]) && $x[3][0] !== '' ? $this->nam4So($x[3][0]) : $nam, $dauDong + $tu + $x[1][1]];
                            }
                        }

                        continue;
                    }
                    preg_match_all('/(?<![\d\/.])(\d{1,2})(?![\d\/.])/', $doan, $ms, PREG_OFFSET_CAPTURE);
                    foreach ($ms[1] as [$so, $viTri]) {
                        if ((int) $so >= 1 && (int) $so <= 31) {
                            $theoThang[] = [(int) $so, $thang, $nam, $dauDong + $tu + $viTri];
                        }
                    }
                }

                continue;
            }

            // "06 - 10/02/2027": ngày đi chỉ ghi ngày, tháng/năm nằm ở ngày về
            if (preg_match('/(?<![\d\/.])(\d{1,2})\s*[-–—]\s*(\d{1,2})[\/.](\d{1,2})(?:[\/.](\d{4}|\d{2}))?(?![\d\/.]*\d)/u', $dong, $mk, PREG_OFFSET_CAPTURE)) {
                [$d1, $d2, $m2] = [(int) $mk[1][0], (int) $mk[2][0], (int) $mk[3][0]];
                $y2 = isset($mk[4]) && $mk[4][0] !== '' ? $this->nam4So($mk[4][0]) : null;
                if ($d1 >= 1 && $d1 <= 31 && $m2 >= 1 && $m2 <= 12) {
                    $m1 = $d1 > $d2 ? ($m2 === 1 ? 12 : $m2 - 1) : $m2;
                    $y1 = $y2 !== null && $d1 > $d2 && $m2 === 1 ? $y2 - 1 : $y2;
                    $khoang[] = [$d1, $m1, $y1, $dauDong + $mk[1][1]];

                    continue;
                }
            }

            preg_match_all('/(?<![\d\/.])(\d{1,2})[\/.](\d{1,2})(?:[\/.](\d{4}|\d{2}))?(?![\d\/.]*\d)/', $dong, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
            $hop = array_values(array_filter($m, fn ($x) => (int) $x[1][0] >= 1 && (int) $x[1][0] <= 31 && (int) $x[2][0] >= 1 && (int) $x[2][0] <= 12));
            if (! $hop) {
                continue;
            }
            $dau = $hop[0];
            $nam = isset($dau[3]) && $dau[3][0] !== '' ? $this->nam4So($dau[3][0]) : null;
            $viTri = $dauDong + $dau[0][1];
            if (count($hop) >= 2) {
                $cuoi = $hop[1];
                // "06/02 – 10/02/2027": năm ghi ở ngày về thì ngày đi cùng năm
                // (hoặc năm trước nếu khoảng vắt qua Tết dương lịch).
                if ($nam === null && isset($cuoi[3]) && $cuoi[3][0] !== '') {
                    $nam = $this->nam4So($cuoi[3][0]) - ((int) $dau[2][0] > (int) $cuoi[2][0] ? 1 : 0);
                }
                $khoang[] = [(int) $dau[1][0], (int) $dau[2][0], $nam, $viTri];
            } else {
                $le[] = [(int) $dau[1][0], (int) $dau[2][0], $nam, $viTri];
            }
        }

        $ds = $khoang ? array_merge($khoang, $theoThang) : array_merge($theoThang, $le);
        if (! $ds && $o['ngay'] instanceof Carbon) {
            // Ô ngày hiển thị kiểu khác ("7 Oct") — lấy ngày/tháng, năm vẫn suy.
            return [[(int) $o['ngay']->day, (int) $o['ngay']->month, null, $this->caODo($o), $o['chu']]];
        }

        // Chữ của dòng chứa ngày — chỉ khi dòng đó có đúng một ngày — để dò mốc
        // Tết ("17/02 (MÙNG 1 TẾT)" → năm có Tết rơi vào 17/02).
        $dongCua = function (int $viTri) use ($o): string {
            $dau = strrpos(substr($o['chu'], 0, $viTri), "\n");
            $cuoi = strpos($o['chu'], "\n", $viTri);

            return substr($o['chu'], $dau === false ? 0 : $dau + 1, ($cuoi === false ? strlen($o['chu']) : $cuoi) - ($dau === false ? 0 : $dau + 1));
        };
        $soTrenDong = [];
        foreach ($ds as $x) {
            $soTrenDong[$dongCua($x[3])] = ($soTrenDong[$dongCua($x[3])] ?? 0) + 1;
        }

        return array_map(function ($x) use ($o, &$doCaO, $dongCua, $soTrenDong) {
            $do = $this->trongDoanDo($o, $x[3]);
            if (! $do) {
                $doCaO ??= $this->caODo($o);
                $do = $doCaO;
            }
            $dong = $dongCua($x[3]);

            return [$x[0], $x[1], $x[2], $do, $soTrenDong[$dong] === 1 ? $dong : ''];
        }, $ds);
    }

    /** Vị trí (byte) trong chữ của ô có nằm trong đoạn tô đỏ không. */
    private function trongDoanDo(array $o, int $viTri): bool
    {
        foreach ($o['do'] ?? [] as [$tu, $den]) {
            if ($viTri >= $tu && $viTri < $den) {
                return true;
            }
        }

        return false;
    }

    /** "THỨ 5", "Thứ 2, 4, 6", "CHỦ NHẬT" → "Thứ 5 hằng tuần". */
    private function lichTuan(string $chu): ?string
    {
        // chuanHoa đổi dấu chấm thành khoảng trắng: "T2.5.7" → "T2 5 7"
        $c = $this->chuanHoa($chu);
        $thu = [];
        if (preg_match_all('/\b(?:THU|T)\s*([2-7](?:\s*(?:,|VA|&|\s)\s*(?:THU\s*|T\s*)?[2-7])*)\b/', $c, $m)) {
            foreach ($m[1] as $nhom) {
                preg_match_all('/[2-7]/', $nhom, $so);
                array_push($thu, ...$so[0]);
            }
        }
        $cn = (bool) preg_match('/\b(CHU NHAT|CN)\b/', $c);
        if (! $thu && ! $cn) {
            return null;
        }
        $thu = array_values(array_unique($thu));
        sort($thu);
        $phan = $thu ? 'Thứ '.implode(', ', $thu) : '';
        if ($cn) {
            $phan = trim($phan.($phan ? ', ' : '').'Chủ nhật');
        }

        return $phan.' hằng tuần';
    }

    /** Mùng 1 Tết âm lịch (dương lịch) — để nhận ra năm từ chữ "MÙNG 1 TẾT". */
    private const NGAY_TET = [
        2024 => '2024-02-10', 2025 => '2025-01-29', 2026 => '2026-02-17', 2027 => '2027-02-06',
        2028 => '2028-01-26', 2029 => '2029-02-13', 2030 => '2030-02-03', 2031 => '2031-01-23',
        2032 => '2032-02-11', 2033 => '2033-01-31',
    ];

    /**
     * Ngày ghi kèm mốc Tết thì biết chắc năm: "17/02 (MÙNG 1 TẾT)" chỉ khớp Tết
     * 2026, "08/02 (M3 Tết)" khớp Tết 2027, "03/02 (27 Tết)" là 3 ngày trước Tết
     * 2027. VGI giữ cả lịch Tết năm nay ("Tháng 02: 17 (Tức 1 Tết Âm Lịch)") —
     * không có mốc này thì dễ hiểu nhầm thành năm sau.
     */
    private function namTheoTet(int $d, int $m, string $chuDong, Carbon $moc): ?int
    {
        if ($chuDong === '' || ! str_contains($k = $this->chuanHoa($chuDong), 'TET')) {
            return null;
        }
        if (preg_match('/\b(?:MUNG|TUC|M)\s*(\d{1,2})\s*(?:TET)\b/', $k, $mm) && (int) $mm[1] >= 1 && (int) $mm[1] <= 10) {
            [$lech, $saiSo] = [(int) $mm[1] - 1, 1];
        } elseif (preg_match('/\b(2\d|30)\s*TET\b/', $k, $mm)) {
            [$lech, $saiSo] = [(int) $mm[1] - 30, 2]; // "27 Tết" = 27 tháng Chạp
        } elseif (preg_match('/\bTET (AM|NGUYEN DAN)\b/', $k)) {
            [$lech, $saiSo] = [0, 7];
        } else {
            return null; // "Tết dương lịch" không cho biết năm
        }

        $khop = [];
        foreach ([$moc->year - 1, $moc->year, $moc->year + 1] as $y) {
            if (! checkdate($m, $d, $y) || ! isset(self::NGAY_TET[$y])) {
                continue;
            }
            $tet = Carbon::parse(self::NGAY_TET[$y])->addDays($lech);
            if (abs(Carbon::create($y, $m, $d)->diffInDays($tet, false)) <= $saiSo) {
                $khop[] = $y;
            }
        }

        return count($khop) === 1 ? $khop[0] : null;
    }

    /**
     * Chốt năm cho cả chuỗi ngày của một tour (theo thứ tự trong sheet).
     *
     * Từng ngày được chốt bằng chotNam (dựa vào dòng trên). Nhưng ngày ĐẦU
     * chuỗi không có dòng trên nên có thể đoán sai năm, kéo sai cả chuỗi: VGI
     * giữ lịch Đông Nam Á cả năm 2026 ("Tháng 01: 21…", "Tháng 08: 31") — đoán
     * "21/01" là 2027 thì "31/08" thành 2027, Tết thành 2028. Nên thử thêm
     * phương án lùi / tiến 1 năm cho ngày đầu, chọn phương án ít ngày "xa vô
     * lý" nhất (đoán ra hơn 10 tháng sau hôm nay — đối tác không mở bán xa thế
     * mà không ghi năm).
     *
     * @param  list<array{ng: array, nam_thang: ?int, nam_muc: ?int, moc: Carbon}>  $cacMuc
     * @return list<?Carbon>
     */
    private function giaiNam(array $cacMuc): array
    {
        $chay = function (?int $lech) use ($cacMuc): array {
            $kq = [];
            $truoc = null;
            $daLech = false;
            foreach ($cacMuc as $x) {
                $ngay = $this->chotNam($x['ng'], $x['nam_thang'], $x['nam_muc'], $x['moc'], $truoc);
                $roRang = $x['ng'][2] !== null || $x['nam_thang'] !== null;
                if ($lech && ! $daLech && ! $roRang && $ngay) {
                    $ngay = checkdate($ngay->month, $ngay->day, $ngay->year + $lech) ? $ngay->copy()->addYears($lech) : $ngay;
                    $daLech = true;
                }
                $kq[] = $ngay;
                $truoc = $ngay ?? $truoc;
            }

            return $kq;
        };
        $diem = function (array $kq) use ($cacMuc): int {
            $d = 0;
            foreach ($kq as $i => $ngay) {
                $x = $cacMuc[$i];
                if (! $ngay || $x['ng'][2] !== null || $x['nam_thang'] !== null) {
                    continue; // ngày ghi rõ năm thì không chấm
                }
                if ($ngay->gt($x['moc']->copy()->addDays(300))) {
                    $d += 10;
                } elseif ($ngay->lt($x['moc']->copy()->subDays(400))) {
                    $d += 1;
                }
            }

            return $d;
        };

        $tot = $chay(null);
        $diemTot = $diem($tot);
        if ($diemTot === 0) {
            return $tot;
        }
        foreach ([-1, 1] as $lech) {
            $thu = $chay($lech);
            if (($d = $diem($thu)) < $diemTot) {
                [$tot, $diemTot] = [$thu, $d];
            }
        }

        return $tot;
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
            // Ngày gần nhất sau dòng trên của cùng tour (cho lùi tối đa 1 tháng):
            // 12/2026 rồi "Tháng 01: 13" là 01/2027; 01/2027 rồi "Tháng 12: 30
            // (tết dương)" là 30/12/2026 (Hanvina). Nhưng nếu ngày đó cách dòng
            // trên hơn 6 tháng thì đây là một danh sách khác của cùng tour (VGI,
            // Triều Hảo xếp nhiều biến thể liền nhau: "...30/12" rồi lại "Tháng 10:
            // 17") → suy năm lại từ đầu, không đẩy sang năm sau.
            $ganNhat = null;
            foreach ([$truoc->year - 1, $truoc->year, $truoc->year + 1] as $y) {
                $x = $tao($y);
                if ($x && $x->gte($truoc->copy()->subDays(31)) && (! $ganNhat || $x->lt($ganNhat))) {
                    $ganNhat = $x;
                }
            }
            if ($ganNhat && $ganNhat->lte($truoc->copy()->addMonths(6))) {
                return $ganNhat;
            }
        }

        if ($namMuc !== null) {
            // Gần mốc nhất trong 2 năm (năm mục, năm mục − 1)
            $hai = array_values(array_filter($ungVien, fn (Carbon $x) => $x->year <= $namMuc));
            usort($hai, fn ($a, $b) => abs($a->diffInDays($moc, false)) <=> abs($b->diffInDays($moc, false)));

            return $hai[0] ?? $ungVien[0];
        }

        // Không có năm nào: chọn năm gần mốc nhất nhưng NGHIÊNG VỀ QUÁ KHỨ — sheet
        // thường giữ lịch cũ từ đầu năm, hiểu nhầm lịch cũ thành năm sau là sinh
        // ra ngày đi giả (Hanvina HCM còn "17/02" của năm nay: 231 ngày trước
        // vs 134 ngày tới → năm nay, đã qua). Ngày quá khứ chỉ thua khi xa hơn
        // 1,75 lần ngày tương lai ("25/12" giữa tháng 10: 285 ngày trước vs 80
        // ngày tới → năm nay, sắp tới; "13/01" → năm sau).
        $qua = null;
        $toi = null;
        foreach ($ungVien as $x) {
            if ($x->lte($moc)) {
                $qua = $x; // ứng viên tăng dần → giữ cái gần mốc nhất
            } elseif (! $toi) {
                $toi = $x;
            }
        }
        if ($qua && $toi) {
            return $qua->diffInDays($moc) <= 1.75 * $moc->diffInDays($toi) ? $qua : $toi;
        }

        return $toi ?? $qua;
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
            if ($x['ngay'] || preg_match('/\d{1,2}\/\d{1,2}/', $x['chu']) || preg_match('/th\p{L}ng\s*\d{1,2}\s*:/iu', $x['chu'])) {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------
    // Số, tiền
    // ------------------------------------------------------------------

    /**
     * Tiền theo chữ hiển thị trong sheet:
     *  - "21.990", COM "1.000", "500", "16.990K", "800k" → ghi theo nghìn, nhân
     *    1.000 (giá tour không ai ghi dưới 100.000đ)
     *  - "2tr5" = 2.500.000, "2tr" / "1 triệu" = triệu
     *  - "Giá cũ: 17990⏎Giá mới: 16990" → giá mới; "Chờ cập nhật" → null
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
        if (preg_match('/(\d+)\s*(?:tr|tri\p{L}u)\s*(\d*)/iu', $chu, $m)) {
            return (int) round(((float) ($m[1].'.'.($m[2] !== '' ? $m[2] : '0'))) * 1000000);
        }
        if (! preg_match('/\d[\d.,]*/', $chu, $m)) {
            return null;
        }
        $chuSo = preg_replace('/\D/', '', $m[0]);
        // Số tiền phải có từ 3 chữ số ("500", "21.990") hoặc ghi đơn vị k
        // ("800k"); số lẻ 1–2 chữ số là chữ thường ("Nhận 2 pax có visa").
        if (strlen($chuSo) < 3 && ! preg_match('/\d\s*k\b/iu', $chu)) {
            return null;
        }
        $so = (int) $chuSo;
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

    /** Số chỗ còn: "-" / "FULL" / "ĐÓNG" = 0, "(2)" (âm, quá chỗ) = 0, "còn 5" = 5, trống = không rõ. */
    private function soChoCon(?array $o): ?int
    {
        if (! $o) {
            return null;
        }
        if ($o['so'] !== null) {
            return max(0, (int) round($o['so']));
        }
        $c = trim($o['chu']);
        $k = $this->chuanHoa($c);
        if ($c === '-' || $c === '–' || preg_match('/^\(\s*\d+\s*\)$/', $c) || preg_match('/\b(FULL|HET|DONG)\b/', $k)) {
            return 0;
        }

        return preg_match('/^(?:CON\s*)?(\d+)/', $k, $m) ? (int) $m[1] : null;
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
    public function khoaTour(string $ten, ?string $hang, ?string $thoiGian = null): string
    {
        $chuyen = null;
        if ($hang && preg_match('/\b([A-Z0-9]{2})\s?(\d{3,4})\b/', $hang, $m)) {
            $chuyen = $m[1].$m[2];
        } elseif ($hang) {
            $chuyen = $this->chuanHoa(strtok($hang, "\n"));
        }
        // Cùng tên nhưng khác số ngày (VVT: "HÀ NỘI - TRÀNG AN..." 3N2Đ / 4N3Đ /
        // 5N4Đ) là các tour khác nhau
        $ngay = $thoiGian ? preg_replace('/\D+/', '', $thoiGian) : '';
        $khoa = $this->chuanHoa($ten).'|'.$chuyen.($ngay !== '' ? '|'.$ngay : '');

        return substr(sha1($khoa), 0, 20);
    }

    /**
     * Cùng tour + cùng ngày (hoặc cùng lịch tuần) xuất hiện nhiều lần (tab Tết
     * lặp tab nước, ô ngày gộp dọc) → giữ một. Số chỗ khác nhau thì lấy số NHỎ
     * hơn cho an toàn.
     */
    private function boTrung(array $dong): array
    {
        $theoKhoa = [];
        foreach ($dong as $d) {
            $k = $d['khoa_tour'].'|'.($d['ngay_di'] ?? 'tuan:'.$d['lich_tuan']);
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
