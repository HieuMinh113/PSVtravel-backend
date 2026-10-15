<?php

namespace Modules\Tour\Services\NhapTour;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Normalizer;

/**
 * Từ dãy "mục" (DocWord / DocPdf) dựng ra dữ liệu tour: tên, số ngày, điểm nổi
 * bật, lịch trình từng ngày (kèm bữa ăn, ảnh), ngày khởi hành + giá, bao gồm /
 * không bao gồm, điều kiện huỷ, lưu ý, hạn nộp visa.
 *
 * Mỗi đối tác trình bày một kiểu (tiêu đề ngày dạng bảng hoặc dạng dòng, bảng
 * giá theo tháng hoặc từng ngày, giá trẻ em theo số tiền hoặc theo %…) nên
 * đọc theo TỪ KHOÁ chứ không theo vị trí cố định. Phần nào không chắc thì ghi
 * vào "canh_bao" để người nhập kiểm tra lại.
 */
class PhanTichChuongTrinh
{
    /** Đầu một dòng mới — không nối vào dòng trước (dùng khi đọc PDF) */
    public const MOC_DONG = '/^\s*(?:[•●▪■◆◦‣∙·✓✔➢➤►»\p{So}\x{F000}-\x{F0FF}\-–+*]|\d{1,2}\s*[h:]\s*\d{0,2}\s*[:\-–]|(?:Sáng|Trưa|Chiều|Tối|SÁNG|TRƯA|CHIỀU|TỐI)\s*[:(]|(?:NGÀY|ĐÊM|Ngày|Đêm)\s*0?\d{1,2}\s*[:.\-–]|(?:Lưu ý|LƯU Ý)\b|\d{1,2}[.)]\s+\p{L})/u';

    private const GACH = '/^\s*(?:[•●▪■◆◦‣∙·✓✔➢➤►»\p{So}\x{F000}-\x{F0FF}*]+|[\-–+](?=\s)|\d{1,2}[.)](?=\s+\p{L})|\?{1,2}(?=\s))\s*/u';

    /**
     * Nước đến hay gặp: tên hiển thị => các cách viết (không dấu, chữ hoa), kèm
     * thành phố / điểm đến nổi tiếng (file chỉ ghi "THƯỢNG HẢI – HÀNG CHÂU").
     * Nước có tên trùng chữ thường khi bỏ dấu (Áo ↔ áo, Mỹ ↔ mỹ phẩm, Nga ↔ ngã,
     * Lào ↔ lão, Bỉ ↔ bi) chỉ nhận qua cách viết dài hơn / tên thành phố.
     */
    private const NUOC = [
        'Trung Quốc' => ['TRUNG QUOC', 'THUONG HAI', 'BAC KINH', 'HANG CHAU', 'TO CHAU', 'TRUONG GIA GIOI', 'PHUONG HOANG CO TRAN',
            'LE GIANG', 'SHANGRI LA', 'CUU TRAI CAU', 'THANH DO', 'TRUNG KHANH', 'QUANG CHAU', 'THAM QUYEN', 'NAM NINH', 'QUE LAM'],
        'Hàn Quốc' => ['HAN QUOC', 'SEOUL', 'BUSAN', 'JEJU', 'NAMI'],
        'Nhật Bản' => ['NHAT BAN', 'TOKYO', 'OSAKA', 'KYOTO', 'NAGOYA', 'HOKKAIDO', 'PHU SI'],
        'Đài Loan' => ['DAI LOAN', 'DAI BAC', 'TAIPEI', 'CAO HUNG'], 'Hồng Kông' => ['HONG KONG'], 'Ma Cao' => ['MA CAO', 'MACAU'],
        'Thái Lan' => ['THAI LAN', 'BANGKOK', 'PATTAYA', 'PHUKET', 'CHIANG MAI'], 'Singapore' => ['SINGAPORE'],
        'Malaysia' => ['MALAYSIA', 'KUALA LUMPUR'], 'Indonesia' => ['INDONESIA', 'BALI'], 'Philippines' => ['PHILIPPINES'],
        'Campuchia' => ['CAMPUCHIA', 'CAMBODIA', 'SIEM REAP', 'PHNOM PENH'], 'Lào' => ['NUOC LAO', 'VIENG CHAN', 'VIENTIANE', 'LUANG PRABANG'],
        'Myanmar' => ['MYANMAR'], 'Ấn Độ' => ['AN DO'], 'Bhutan' => ['BHUTAN'], 'Nepal' => ['NEPAL'],
        'Sri Lanka' => ['SRI LANKA'], 'Maldives' => ['MALDIVES'], 'Mông Cổ' => ['MONG CO'],
        'Úc' => ['UC', 'AUSTRALIA', 'SYDNEY', 'MELBOURNE'], 'New Zealand' => ['NEW ZEALAND'],
        'Dubai' => ['DUBAI', 'UAE'], 'Thổ Nhĩ Kỳ' => ['THO NHI KY'], 'Ai Cập' => ['AI CAP'],
        'Nga' => ['NUOC NGA', 'LIEN BANG NGA', 'MATXCOVA', 'MOSCOW'], 'Pháp' => ['PHAP', 'PARIS'], 'Ý' => ['ITALIA', 'ITALY', 'ROME', 'VENICE'],
        'Đức' => ['NUOC DUC', 'BERLIN', 'MUNICH', 'FRANKFURT'], 'Thụy Sĩ' => ['THUY SI'],
        'Anh' => ['VUONG QUOC ANH', 'LONDON'], 'Hà Lan' => ['HA LAN', 'AMSTERDAM'], 'Bỉ' => ['BRUSSELS', 'NUOC BI'], 'Áo' => ['NUOC AO', 'VIENNA'],
        'Tây Ban Nha' => ['TAY BAN NHA'], 'Bồ Đào Nha' => ['BO DAO NHA'], 'Hy Lạp' => ['HY LAP'], 'Séc' => ['CH SEC', 'PRAHA'],
        'Mỹ' => ['HOA KY', 'NUOC MY', 'NEW YORK', 'LOS ANGELES', 'LAS VEGAS'], 'Canada' => ['CANADA'], 'Kazakhstan' => ['KAZAKHSTAN'], 'Uzbekistan' => ['UZBEKISTAN'],
        'Georgia' => ['GEORGIA'],
    ];

    /** Nước => vùng (khớp cách đặt "Vùng / khu vực" của tour mẫu) */
    private const VUNG = [
        'Trung Quốc' => 'Trung Quốc', 'Đài Loan' => 'Đông Bắc Á', 'Hồng Kông' => 'Trung Quốc', 'Ma Cao' => 'Trung Quốc',
        'Hàn Quốc' => 'Đông Bắc Á', 'Nhật Bản' => 'Đông Bắc Á', 'Mông Cổ' => 'Đông Bắc Á',
        'Thái Lan' => 'Đông Nam Á', 'Singapore' => 'Đông Nam Á', 'Malaysia' => 'Đông Nam Á', 'Indonesia' => 'Đông Nam Á',
        'Philippines' => 'Đông Nam Á', 'Campuchia' => 'Đông Nam Á', 'Lào' => 'Đông Nam Á', 'Myanmar' => 'Đông Nam Á',
        'Ấn Độ' => 'Nam Á', 'Bhutan' => 'Nam Á', 'Nepal' => 'Nam Á', 'Sri Lanka' => 'Nam Á', 'Maldives' => 'Nam Á',
        'Úc' => 'Châu Úc', 'New Zealand' => 'Châu Úc', 'Dubai' => 'Trung Đông', 'Thổ Nhĩ Kỳ' => 'Trung Đông', 'Ai Cập' => 'Châu Phi',
        'Mỹ' => 'Châu Mỹ', 'Canada' => 'Châu Mỹ',
        'Nga' => 'Châu Âu', 'Pháp' => 'Châu Âu', 'Ý' => 'Châu Âu', 'Đức' => 'Châu Âu', 'Thụy Sĩ' => 'Châu Âu', 'Anh' => 'Châu Âu',
        'Hà Lan' => 'Châu Âu', 'Bỉ' => 'Châu Âu', 'Áo' => 'Châu Âu', 'Tây Ban Nha' => 'Châu Âu', 'Bồ Đào Nha' => 'Châu Âu',
        'Hy Lạp' => 'Châu Âu', 'Séc' => 'Châu Âu',
    ];

    /** @var list<array> các mục đã chuẩn hoá */
    private array $ds = [];

    /** @var list<string> */
    private array $canhBao = [];

    /**
     * @param  list<array>  $muc
     * @return array<string, mixed>
     */
    public function phanTich(array $muc, string $tenFile = ''): array
    {
        $this->canhBao = [];
        $this->ds = $this->chuanHoaMuc($muc);
        $toanVan = implode("\n", array_map(fn ($m) => $m['chu'] ?? '', $this->ds));

        // ---- Tiêu đề từng ngày → khoảng của lịch trình ----
        $ngay = $this->cacNgay();

        $dauLichTrinh = $ngay ? $ngay[0]['i'] : count($this->ds);
        $cuoiLichTrinh = $ngay ? $this->cuoiNgayCuoi($ngay[count($ngay) - 1]['i']) : count($this->ds);

        // ---- Giá + ngày khởi hành (bảng giá nằm trước hoặc sau lịch trình) ----
        [$dot, $boQua, $giaPhu, $chuyenBay] = $this->bangGia($toanVan);

        // ---- Các phần có tiêu đề (bao gồm, không bao gồm, huỷ, lưu ý…) ----
        $phan = $this->cacPhan(array_merge(range(0, max(0, $dauLichTrinh - 1)), $cuoiLichTrinh < count($this->ds) ? range($cuoiLichTrinh, count($this->ds) - 1) : []), $dauLichTrinh);

        // ---- Lịch trình ----
        $lichTrinh = [];
        $anh = [];
        foreach ($ngay as $k => $n) {
            $het = isset($ngay[$k + 1]) ? $ngay[$k + 1]['i'] : $cuoiLichTrinh;
            $dong = [];
            $bua = $n['bua'];
            for ($i = $n['i'] + 1; $i < $het; $i++) {
                $m = $this->ds[$i];
                if ($m['loai'] === 'anh') {
                    $anh[] = $m + ['ngay' => $n['so']];

                    continue;
                }
                if ($i === $n['i'] + 1 && $n['cot_bua'] && $m['o'] && count($m['o']) === $n['so_o']) {
                    $bua = $this->buaTuHang($n['cot_bua'], $m['o']) ?? $bua;

                    continue;
                }
                // PDF: hàng "• | • | •" dưới cột Sáng / Trưa / Tối
                if ($i <= $n['i'] + 2 && ! $n['cot_bua'] && ! $bua && $m['o'] && $this->laHangBua($m['o'])) {
                    $bua = $this->buaTuHang([0 => 'sáng', 1 => 'trưa', 2 => 'tối'], $m['o']);

                    continue;
                }
                $chu = $m['o'] ? implode(' ', array_filter($m['o'], fn ($x) => $x !== '' && $x !== '•')) : $m['chu'];
                if ($chu === '' || (mb_strlen($chu) <= 2 && ! preg_match('/\d/', $chu))
                    || preg_match('/^(?:\s|\||•|sáng|trưa|chiều|tối)+$/iu', $chu) || preg_match('/^túc$/iu', $chu)) {
                    continue; // "M", "B", "túc" — chữ trong ô bữa ăn bị ngắt dòng trong file PDF
                }
                $dong[] = ($m['gach'] ? '• ' : '').$chu;
            }
            $lichTrinh[] = [
                'ngay' => $n['so'],
                'tieu_de' => $n['tieu_de'],
                'bua' => $bua,
                'noi_dung' => implode("\n", $dong),
            ];
        }
        if (! $ngay) {
            $this->canhBao[] = 'Không tìm thấy lịch trình từng ngày (các dòng "NGÀY 1: …") — nhập tay phần lịch trình.';
        }

        // Ảnh ngoài lịch trình (trang bìa, phần giá…) → ảnh chung của tour
        foreach ($this->ds as $i => $m) {
            if ($m['loai'] === 'anh' && ($i < $dauLichTrinh || $i >= $cuoiLichTrinh)) {
                $anh[] = $m + ['ngay' => null];
            }
        }
        $anh = $this->locAnh($anh);

        // ---- Phần đầu: tên, thời gian, nổi bật, giới thiệu ----
        $dau = array_slice($this->ds, 0, $dauLichTrinh, true);
        [$soNgay, $soDem] = $this->thoiGian($dau, $tenFile, count($lichTrinh));
        $nuoc = $this->nuoc($toanVan);
        $ten = $this->ten($dau, $tenFile, $lichTrinh);

        $noiBat = [];
        $gioiThieu = [];
        $khauHieu = null;
        $sauDacBiet = false;
        foreach ($dau as $i => $m) {
            if ($m['loai'] !== 'chu' || isset($phan['thuoc'][$i]) || $this->coNgayVaGia($m)) {
                continue;
            }
            $chu = $m['chu'];
            if (preg_match('/^(thời gian|khởi hành|hành trình|hãng hàng không|hàng không|phương tiện)\s*:/iu', $chu)) {
                continue;
            }
            if (preg_match('/^đặc biệt\s*:?$/iu', $chu)) {
                $sauDacBiet = true;

                continue;
            }
            if ($m['gach'] || $sauDacBiet) {
                $noiBat[] = $chu;
            } elseif (mb_strlen($chu) >= 100) {
                $gioiThieu[] = $chu;
            } elseif (! $khauHieu && preg_match('/^(khám phá|hành trình|trải nghiệm|chương trình|du lịch)\b/iu', $chu) && $chu !== $ten['goc']) {
                $khauHieu = $this->hoaDau($chu, cau: true);
            }
        }
        foreach ($phan['noi_bat'] as $p) {
            $noiBat = array_merge($noiBat, $p['dong']);
        }
        $noiBat = array_values(array_unique(array_map(fn ($x) => Str::limit(rtrim(preg_replace('/^•\s*/u', '', $x), ' .'), 200, ''), $noiBat)));

        // ---- Giá cơ bản: rẻ nhất trong các ngày còn đi được ----
        $gia = fn (string $cot) => collect($dot)->pluck('gia.'.$cot)->filter()->min();
        $giaTreEm = $gia('tre_em');
        $giaEmBe = $gia('em_be');
        $phanTram = $this->phanTramTreEm($toanVan);
        $giaNguoiLon = $gia('nguoi_lon');
        if (! $giaTreEm && $phanTram['tre_em'] && $giaNguoiLon) {
            $giaTreEm = (int) round($giaNguoiLon * $phanTram['tre_em'] / 100 / 1000) * 1000;
            $this->canhBao[] = "Giá trẻ em tính theo {$phanTram['tre_em']}% giá người lớn như trong file — kiểm tra lại.";
        }
        if (! $giaEmBe && $phanTram['em_be'] && $giaNguoiLon) {
            $giaEmBe = (int) round($giaNguoiLon * $phanTram['em_be'] / 100 / 1000) * 1000;
            $this->canhBao[] = "Giá em bé tính theo {$phanTram['em_be']}% giá người lớn như trong file — kiểm tra lại.";
        }

        // ---- Lưu ý ----
        $luuY = [];
        foreach ($phan['luu_y'] as $p) {
            if ($p['dong']) {
                $luuY[] = ['title' => $p['tieu_de'], 'content' => implode("\n", $p['dong'])];
            }
        }
        if ($phan['chung']) {
            $luuY[] = ['title' => 'Lưu ý về chương trình', 'content' => implode("\n", $phan['chung'])];
        }
        if ($chuyenBay) {
            $luuY[] = ['title' => 'Chuyến bay dự kiến', 'content' => implode("\n", $chuyenBay)];
        }
        if ($bangChiTiet = $this->bangGiaChiTiet($dot, $giaPhu)) {
            $luuY[] = ['title' => 'Bảng giá theo ngày khởi hành', 'content' => $bangChiTiet];
        }

        if (! $dot && ! $boQua) {
            $this->canhBao[] = 'Không tìm thấy bảng giá / ngày khởi hành — thêm tay ở "Lịch khởi hành".';
        }
        if ($boQua) {
            $this->canhBao[] = 'Bỏ qua '.count($boQua).' ngày khởi hành đã qua: '.implode(', ', $boQua).'.';
            if (! $dot) {
                $this->canhBao[] = 'Mọi ngày khởi hành trong file đều đã qua — giá lấy theo bảng giá cũ, thêm ngày mới ở "Lịch khởi hành".';
            }
        }
        if ($lichTrinh && $soNgay && count($lichTrinh) !== $soNgay) {
            $this->canhBao[] = 'Lịch trình có '.count($lichTrinh)." ngày nhưng thời gian tour ghi {$soNgay} ngày — kiểm tra lại.";
        }
        // Ngày gần quá, không còn kịp nộp hồ sơ visa — vẫn tạo, nhưng báo để đóng bán nếu cần
        $hanVisa = $this->hanVisa($toanVan);
        $quaHanVisa = $hanVisa && $nuoc ? collect($dot)->filter(fn ($d) => Carbon::parse($d['ngay'])->subDays($hanVisa)->lt(today()))
            ->map(fn ($d) => Carbon::parse($d['ngay'])->format('d/m'))->values() : collect();
        if ($quaHanVisa->isNotEmpty()) {
            $this->canhBao[] = $quaHanVisa->count()." ngày khởi hành đã quá hạn nộp hồ sơ visa (trước {$hanVisa} ngày): "
                .$quaHanVisa->implode(', ').' — vẫn được tạo, đóng bán ở "Lịch khởi hành" nếu không kịp làm visa.';
        }
        if (! $nuoc && ! preg_match('/\b(việt nam)\b/iu', $toanVan)) {
            $this->canhBao[] = 'Không nhận ra nước đến — chọn loại tour / quốc gia trong form.';
        }

        return self::sachUtf8([
            'ten' => $ten['ten'] ? trim($ten['ten'].($soNgay ? " {$soNgay}N{$soDem}Đ" : '')) : null,
            'mo_ta' => $gioiThieu ? implode("\n\n", $gioiThieu) : $khauHieu,
            'so_ngay' => $soNgay,
            'so_dem' => $soDem,
            'khoi_hanh_tu' => $this->noiKhoiHanh($lichTrinh),
            'loai' => $nuoc ? 'abroad' : 'domestic',
            'quoc_gia' => $nuoc,
            'vung' => $nuoc ? (self::VUNG[$nuoc] ?? null) : null,
            'nhan' => preg_match('/no\s*-?\s*shop/i', $toanVan) ? 'No Shopping' : null,
            'noi_bat' => array_slice($noiBat, 0, 12),
            'lich_trinh' => $lichTrinh,
            'dot' => $dot,
            'dot_bo_qua' => $boQua,
            'gia_nguoi_lon' => $giaNguoiLon ?? collect($this->giaCu ?? [])->min(),
            'gia_tre_em' => $giaTreEm,
            'gia_em_be' => $giaEmBe,
            // (không dùng ltrim($x, '• '): ltrim cắt theo TỪNG BYTE nên "✓", "“", "–" ở đầu
            // mục bị cắt mất nửa ký tự → chuỗi hỏng UTF-8, lưu tour báo lỗi "Malformed UTF-8")
            'bao_gom' => collect($phan['bao_gom'])->flatMap(fn ($p) => $p['dong'])->map(fn ($x) => preg_replace('/^[•\s]+/u', '', $x))->values()->all(),
            'khong_bao_gom' => collect($phan['khong_bao_gom'])->flatMap(fn ($p) => $p['dong'])->map(fn ($x) => preg_replace('/^[•\s]+/u', '', $x))->values()->all(),
            'huy' => implode("\n", collect($phan['huy'])->flatMap(fn ($p) => $p['dong'])->all()) ?: null,
            'luu_y' => $luuY,
            'han_visa_ngay' => $hanVisa,
            'anh' => $anh,
            'canh_bao' => $this->canhBao,
        ]);
    }

    /**
     * Chốt chặn cuối: mọi chuỗi chữ trả ra đều là UTF-8 hợp lệ (byte hỏng thay
     * bằng "?") — một ký tự lạ trong file không được làm hỏng cả lần nhập tour.
     * Bỏ qua dữ liệu ảnh (nhị phân).
     */
    private static function sachUtf8(mixed $v, ?string $khoa = null): mixed
    {
        if (is_string($v)) {
            return $khoa === 'du_lieu' || mb_check_encoding($v, 'UTF-8') ? $v : mb_scrub($v, 'UTF-8');
        }
        if (is_array($v)) {
            foreach ($v as $k => $x) {
                $v[$k] = self::sachUtf8($x, is_string($k) ? $k : $khoa);
            }
        }

        return $v;
    }

    // ===================================================================
    // Chuẩn hoá chữ
    // ===================================================================

    /** @return list<array> */
    private function chuanHoaMuc(array $muc): array
    {
        $ra = [];
        foreach ($muc as $m) {
            if ($m['loai'] === 'anh') {
                $ra[] = $m;

                continue;
            }
            $o = isset($m['o']) && $m['o'] ? array_map(fn ($x) => self::chuan($x), $m['o']) : null;
            $chu = $o ? implode(' | ', $o) : self::chuan($m['chu']);
            $gach = (bool) ($m['gach'] ?? false);
            if (! $o && preg_match(self::GACH, $chu)) {
                $chu = trim(preg_replace(self::GACH, '', $chu));
                $gach = $gach || $chu !== '';
            }
            if ($chu === '' || preg_match('/^[\s|]*$/u', $chu) && ! $o) {
                continue;
            }
            $ra[] = ['loai' => 'chu', 'chu' => $chu, 'dam' => (bool) ($m['dam'] ?? false), 'gach' => $gach, 'o' => $o];
        }

        return $ra;
    }

    /** Chuẩn hoá một chuỗi: dựng sẵn dấu (NFC), bỏ ký tự vô hình, đổi chữ "giả" về chữ thường. */
    public static function chuan(string $s): string
    {
        $s = Normalizer::normalize($s, Normalizer::FORM_C) ?: $s;
        // Chữ Nga / chữ hoa nhỏ trông y hệt chữ Latin — hay gặp khi chép từ web
        $s = strtr($s, [
            'а' => 'a', 'е' => 'e', 'о' => 'o', 'р' => 'p', 'с' => 'c', 'у' => 'y', 'х' => 'x', 'ѕ' => 's', 'і' => 'i', 'ј' => 'j', 'ԁ' => 'd', 'ɡ' => 'g',
            'А' => 'A', 'В' => 'B', 'Е' => 'E', 'К' => 'K', 'М' => 'M', 'Н' => 'H', 'О' => 'O', 'Р' => 'P', 'С' => 'C', 'Т' => 'T', 'Х' => 'X',
            'ᴀ' => 'a', 'ʙ' => 'b', 'ᴄ' => 'c', 'ᴅ' => 'd', 'ᴇ' => 'e', 'ɢ' => 'g', 'ʜ' => 'h', 'ɪ' => 'i', 'ᴊ' => 'j', 'ᴋ' => 'k', 'ʟ' => 'l',
            'ᴍ' => 'm', 'ɴ' => 'n', 'ᴏ' => 'o', 'ᴘ' => 'p', 'ʀ' => 'r', 'ꜱ' => 's', 'ᴛ' => 't', 'ᴜ' => 'u', 'ᴠ' => 'v', 'ᴡ' => 'w', 'ʏ' => 'y', 'ᴢ' => 'z',
        ]);
        // Ký hiệu của phông Symbol / Wingdings (gạch đầu dòng, dấu tích) nằm ở vùng riêng U+F0xx
        $s = preg_replace('/[\x{F000}-\x{F0FF}]/u', '•', $s);
        $s = preg_replace('/[\x{200B}-\x{200D}\x{2060}\x{FEFF}\x{00AD}]/u', '', $s);
        $s = preg_replace('/[\t\x{00A0}\x{2000}-\x{200A}\x{202F}\x{3000}]+/u', ' ', $s);
        $s = preg_replace('/ {2,}/', ' ', $s);

        return trim($s);
    }

    /** Bỏ dấu, viết hoa — để so từ khoá */
    private static function khongDau(string $s): string
    {
        return strtoupper(Str::ascii($s));
    }

    /** CHỮ HOA TOÀN BỘ → "Chữ Hoa Đầu Từ" (hoặc chỉ hoa đầu câu). Chuỗi không viết hoa toàn bộ thì giữ nguyên. */
    private function hoaDau(string $s, bool $cau = false): string
    {
        $chuCai = preg_replace('/[^\p{L}]/u', '', $s);
        if ($chuCai === '' || mb_strlen(preg_replace('/[^\p{Lu}]/u', '', $chuCai)) < mb_strlen($chuCai) * 0.7) {
            return $s;
        }
        $thuong = mb_strtolower($s);
        if ($cau) {
            return mb_strtoupper(mb_substr($thuong, 0, 1)).mb_substr($thuong, 1);
        }

        $thuong = preg_replace('/\btp\.\s*(?=\p{L})/u', 'TP. ', $thuong);

        return preg_replace_callback('/(^|[\s(\/–\-“"‘\'])(\p{L})/u', fn ($k) => $k[1].mb_strtoupper($k[2]),
            preg_replace_callback('/\b(tp|hcm|mb|vn|usa|uae)\b/u', fn ($k) => mb_strtoupper($k[1]), $thuong));
    }

    // ===================================================================
    // Lịch trình
    // ===================================================================

    /**
     * Các tiêu đề ngày của lịch trình.
     *
     * File có thể có HAI lượt "Ngày 1 … Ngày N": bảng "lịch trình rút gọn" ở
     * đầu (mỗi ngày một hàng) rồi mới tới lịch trình chi tiết. Chia các tiêu
     * đề thành từng lượt (đánh số lại từ 1 là sang lượt mới) và lấy lượt có
     * nhiều nội dung nhất giữa các ngày — tức lịch trình chi tiết.
     *
     * @return list<array>
     */
    private function cacNgay(): array
    {
        $luot = [];
        foreach ($this->ds as $i => $m) {
            if ($m['loai'] !== 'chu' || ! ($t = $this->tieuDeNgay($m))) {
                continue;
            }
            $t = ['i' => $i] + $t;
            $cuoi = $luot ? count($luot) - 1 : null;
            if ($cuoi !== null && $t['so'] > $luot[$cuoi][count($luot[$cuoi]) - 1]['so']) {
                $luot[$cuoi][] = $t;
            } elseif ($cuoi === null || $t['so'] === 1) {
                $luot[] = [$t];
            }
            // còn lại: "Ngày 2" nhắc lại trong phần lưu ý — không phải tiêu đề mới
        }
        $diem = fn (array $ds) => array_sum(array_map(
            fn ($k) => array_sum(array_map(fn ($m) => mb_strlen($m['chu'] ?? ''), array_slice($this->ds, $ds[$k]['i'] + 1, $ds[$k + 1]['i'] - $ds[$k]['i'] - 1))),
            array_keys(array_slice($ds, 0, -1))
        ));
        $chon = [];
        foreach ($luot as $ds) {
            if (! $chon || $diem($ds) >= $diem($chon)) {
                $chon = $ds;
            }
        }

        return $chon;
    }

    /**
     * "NGÀY 2: THƯỢNG HẢI – Ô TRẤN (ĂN S, T, C)", "NGÀY 1  HÀ NỘI - SEOUL" (không
     * dấu hai chấm) hoặc hàng bảng "NGÀY 1 | HÀ NỘI – BẮC KINH | (Ăn trưa, tối)".
     */
    private function tieuDeNgay(array $m): ?array
    {
        $chu = $m['o'][0] ?? $m['chu'];
        if (! preg_match('/^(NGÀY|NGAY|ĐÊM|DEM|Ngày|Đêm)\s*0?(\d{1,2})(\s*[:.\-–]\s*|\s+|$)(.*)$/u', $chu, $k)) {
            return null;
        }
        if (! ($m['dam'] || $m['o'] || mb_strtoupper($k[1]) === $k[1])) {
            return null;
        }
        $tieuDe = trim($k[4]);
        // Không có dấu ngăn sau số ngày: phần sau phải là tên chặng viết hoa
        // ("NGÀY 1  HÀ NỘI - SEOUL"), không phải câu thường ("Ngày 1 tháng 5…")
        if (trim($k[3]) === '' && $tieuDe !== '' && ! $this->laChuHoa($tieuDe)) {
            return null;
        }
        $bua = null;
        if ($m['o']) {
            foreach (array_slice($m['o'], 1) as $o) {
                if (preg_match('/^\((.*)\)$/u', $o, $b)) {
                    $bua = $this->buaNgoac($b[1]);
                } elseif ($tieuDe === '' && ! preg_match('/^(?:\s|•|sáng|trưa|chiều|tối)*$/iu', $o)) {
                    $tieuDe = $o;
                }
            }
        }
        if (preg_match('/\(([^()]*)\)\s*$/u', $tieuDe, $b) && preg_match('/\b(ăn|nghỉ|ngủ|máy bay|MB|S|T|C|sáng|trưa|tối)\b/iu', $b[1])) {
            $bua = $this->buaNgoac($b[1]);
            $tieuDe = trim(mb_substr($tieuDe, 0, mb_strrpos($tieuDe, '(')));
        }
        $tieuDe = preg_replace('/^[\s|:–\-]+|[\s|:–\-]+$/u', '', $tieuDe); // trim() cắt theo byte, hỏng chữ "ồ", "“"…
        if ($tieuDe === '' && ! $m['o']) {
            return null;
        }

        // Cột bữa ăn: hàng tiêu đề có các ô "Sáng / Trưa / Tối"
        $cot = [];
        foreach ($m['o'] ?? [] as $j => $o) {
            if (preg_match('/^(Sáng|Trưa|Chiều|Tối)\b/iu', $o, $b)) {
                $cot[$j] = mb_strtolower($b[1]);
            }
        }
        // PDF: ba ô đánh dấu bữa nằm ngay sau tên ngày ("NGÀY 4: … | • | • | Tự túc")
        if (! $cot && ! $bua && $m['o'] && count($m['o']) === 4 && $this->laHangBua(array_slice($m['o'], 1))) {
            $bua = $this->buaTuHang([1 => 'sáng', 2 => 'trưa', 3 => 'tối'], $m['o']);
        }

        return [
            'so' => (int) $k[2],
            'tieu_de' => $this->hoaDau($tieuDe),
            'bua' => $bua,
            'cot_bua' => $cot,
            'so_o' => count($m['o'] ?? []),
        ];
    }

    /** Viết hoa toàn bộ (≥ 70% chữ cái là chữ hoa) */
    private function laChuHoa(string $s): bool
    {
        $chuCai = preg_replace('/[^\p{L}]/u', '', $s);

        return $chuCai !== '' && mb_strlen(preg_replace('/[^\p{Lu}]/u', '', $chuCai)) >= mb_strlen($chuCai) * 0.7;
    }

    /** "(ĂN S, T, C)" → "Ăn sáng, trưa, tối"; "(Nghỉ trên máy bay, T, C)" → "Nghỉ trên máy bay, trưa, tối" */
    private function buaNgoac(string $s): string
    {
        $s = $this->hoaDau(trim($s), cau: true);
        $s = preg_replace_callback('/(?<![\p{L}])([STC])(?![\p{L}])/iu', fn ($k) => ['s' => 'sáng', 't' => 'trưa', 'c' => 'tối'][mb_strtolower($k[1])], $s);

        return mb_strtoupper(mb_substr($s, 0, 1)).mb_substr($s, 1);
    }

    /** Ba ô chỉ có dấu đánh bữa ("•", "MB", "Tự túc"…) — hàng bữa ăn Sáng / Trưa / Tối của file PDF */
    private function laHangBua(array $o): bool
    {
        return count($o) === 3 && collect($o)->every(fn ($x) => preg_match('/^(?:[•xX✓✔√]|m\s*b|tự(?:\s*túc)?|)$/iu', trim($x)))
            && collect($o)->contains(fn ($x) => trim($x) !== '');
    }

    /** Hàng đánh dấu bữa ăn ngay dưới tiêu đề ngày: "•" = có ăn, "MB" = ăn trên máy bay, chữ khác giữ nguyên. */
    private function buaTuHang(array $cot, array $o): ?string
    {
        $co = [];
        $khac = [];
        foreach ($cot as $j => $buoi) {
            $v = trim($o[$j] ?? '');
            if ($v === '') {
                continue;
            }
            if (preg_match('/^[•xX✓✔√v]+$/u', $v)) {
                $co[] = $buoi;
            } elseif (preg_match('/^m\s*b$/iu', $v)) {
                $khac[] = ucfirst($buoi).': trên máy bay';
            } elseif (preg_match('/^tự(\s*túc)?$/iu', $v)) {
                $khac[] = ucfirst($buoi).': tự túc';
            } else {
                $khac[] = ucfirst($buoi).': '.mb_strtolower($v);
            }
        }
        $phan = array_filter([$co ? 'Ăn '.implode(', ', $co) : null, ...$khac]);

        return $phan ? implode(' · ', $phan) : null;
    }

    /** Ngày cuối kết thúc ở: tiêu đề phần kế tiếp, bảng giá (cả hàng tiêu đề bảng), hoặc dòng "Lưu ý" chung của chương trình. */
    private function cuoiNgayCuoi(int $batDau): int
    {
        for ($i = $batDau + 1; $i < count($this->ds); $i++) {
            $m = $this->ds[$i];
            if ($m['loai'] !== 'chu') {
                continue;
            }
            if ($this->loaiTieuDe($m) || $this->coNgayVaGia($m) || $this->laLuuYChung($m['chu'])
                || ($m['o'] && $this->laDongTieuDeGia($m['chu']))) { // hàng tiêu đề bảng giá "KHỞI HÀNH | NGƯỜI LỚN | TRẺ EM
                return $i;
            }
        }

        return count($this->ds);
    }

    private function laLuuYChung(string $chu): bool
    {
        return (bool) preg_match('/^(lưu ý|ghi chú)\s*:|thứ tự (các điểm|chương trình|tham quan)|(các điểm tham quan|lịch trình).{0,40}(thay đổi|linh động)/iu', $chu);
    }

    // ===================================================================
    // Các phần có tiêu đề
    // ===================================================================

    /** Loại phần của một dòng tiêu đề, null = không phải tiêu đề */
    private function loaiTieuDe(array $m): ?array
    {
        $chu = $m['o'] ? ($m['o'][0] ?? '') : $m['chu'];
        $hoa = mb_strtoupper($chu) === $chu;
        // Dòng có gạch đầu dòng chỉ là tiêu đề khi in đậm, viết hoa và kết thúc bằng
        // dấu hai chấm hoặc là tên phần rõ ràng ("☞ GIÁ TOUR BAO GỒM :", "☞ HỒ SƠ VISA ĐOÀN")
        $gachLaTieuDe = $m['gach'] && $m['dam'] && $hoa
            && (preg_match('/[:：]\s*$/u', $chu) || preg_match('/BAO GOM|\bHUY\b|HO SO|LUU Y|DIEU KIEN/', self::khongDau($chu)));
        if (($m['gach'] && ! $gachLaTieuDe) || mb_strlen($chu) > 90 || $chu === '') {
            return null;
        }
        if (! $m['dam'] && ! $hoa) {
            return null;
        }
        // Câu in đậm trong lịch trình ("Sáng: … làm thủ tục trả phòng") không phải tiêu đề
        if (preg_match('/^\s*(?:\d{1,2}\s*[h:]\s*\d{0,2}|(?:sáng|trưa|chiều|tối|đêm)\b)/iu', $chu)) {
            return null;
        }
        // Từ khoá của tiêu đề nằm ở đầu dòng
        $k = mb_substr(self::khongDau($chu), 0, 45);
        $loai = match (true) {
            str_contains($k, 'KHONG BAO GOM') || str_contains($k, 'CHUA BAO GOM') => 'khong_bao_gom',
            str_contains($k, 'BAO GOM') => 'bao_gom',
            (bool) preg_match('/\bHUY\b/', $k) => 'huy',
            (bool) preg_match('/NOI BAT|DIEM NHAN|DAC SAC/', $k) => 'noi_bat',
            (bool) preg_match('/^(BANG GIA|GIA TOUR TRON GOI)/', trim($k, ' *')) => 'gia',
            (bool) preg_match('/LICH TRINH|TIEU CHUAN PHUC VU|^CHUONG TRINH/', $k) => 'bo_qua',
            (bool) preg_match('/LUU Y|GHI CHU|TRE EM|THANH TOAN|DANG KY|HO SO|VISA|DIEU KIEN|QUY DINH|CHINH SACH|THU TUC/', $k) => 'luu_y',
            default => null,
        };
        if (! $loai) {
            return null;
        }

        return ['loai' => $loai, 'tieu_de' => $this->hoaDau(trim(preg_replace('/\s*[:：]\s*$/u', '', trim($chu, ' *_'))), cau: true)];
    }

    /**
     * Hàng tiêu đề bảng hai cột ("DỊCH VỤ BAO GỒM | DỊCH VỤ KHÔNG BAO GỒM"):
     * mọi ô có chữ đều là tiêu đề phần, và ít nhất hai loại khác nhau.
     *
     * @return array<int, array{loai:string, tieu_de:string}>|null
     */
    private function tieuDeHaiCot(array $o): ?array
    {
        $cot = [];
        foreach ($o as $j => $chu) {
            if (trim($chu) === '') {
                continue;
            }
            $t = $this->loaiTieuDe(['o' => null, 'chu' => $chu, 'gach' => false, 'dam' => true]);
            if (! $t || in_array($t['loai'], ['gia', 'bo_qua'], true)) {
                return null;
            }
            $cot[$j] = $t;
        }

        return count(array_unique(array_column($cot, 'loai'))) >= 2 ? $cot : null;
    }

    /**
     * Chia các dòng ngoài lịch trình theo tiêu đề phần.
     *
     * @param  list<int>  $chiSo
     */
    private function cacPhan(array $chiSo, int $dauLichTrinh): array
    {
        $kq = ['bao_gom' => [], 'khong_bao_gom' => [], 'huy' => [], 'noi_bat' => [], 'luu_y' => [], 'chung' => [], 'thuoc' => []];
        $hienTai = null;
        $cotPhan = null; // bảng hai cột "BAO GỒM | KHÔNG BAO GỒM": cột → loại phần
        $sauLichTrinh = false;
        foreach ($chiSo as $i) {
            $m = $this->ds[$i];
            if ($m['loai'] !== 'chu') {
                continue;
            }
            if ($i >= $dauLichTrinh && ! $sauLichTrinh) {
                $sauLichTrinh = true;
                $hienTai = null; // phần trước lịch trình không kéo dài qua lịch trình
                $cotPhan = null;
            }
            // Hàng tiêu đề của bảng hai cột: mỗi ô là tiêu đề một phần
            if ($m['o'] && count($m['o']) >= 2 && ($cot = $this->tieuDeHaiCot($m['o']))) {
                $kq['thuoc'][$i] = true;
                $cotPhan = [];
                foreach ($cot as $j => $t) {
                    $kq[$t['loai']][] = ['tieu_de' => $t['tieu_de'], 'dong' => []];
                    $cotPhan[$j] = $t['loai'];
                }
                $hienTai = $cotPhan[array_key_first($cotPhan)];

                continue;
            }
            // (ô in đậm có chữ "visa", "hồ sơ"… mà bắt đầu bằng gạch đầu dòng vẫn là mục trong bảng)
            if ($cotPhan !== null && (! $this->loaiTieuDe($m) || ($m['o'] && preg_match(self::GACH, $m['o'][0])))) {
                $kq['thuoc'][$i] = true;
                // Ô bắt đầu bằng gạch đầu dòng = mục mới; không thì là phần chữ bị
                // xuống dòng của mục trước trong cùng cột
                foreach ($m['o'] ?? [($m['gach'] ? '- ' : '').$m['chu']] as $j => $o) {
                    $loai = $cotPhan[$j] ?? null;
                    $o = trim($o);
                    if (! $loai || $o === '') {
                        continue;
                    }
                    $dong = &$kq[$loai][count($kq[$loai]) - 1]['dong'];
                    if (preg_match(self::GACH, $o) || ! $dong) {
                        $dong[] = '• '.trim(preg_replace(self::GACH, '', $o));
                    } else {
                        $dong[count($dong) - 1] .= ' '.$o;
                    }
                    unset($dong);
                }

                continue;
            }
            if ($t = $this->loaiTieuDe($m)) {
                $cotPhan = null;
                $kq['thuoc'][$i] = true;
                if (in_array($t['loai'], ['gia', 'bo_qua'], true)) {
                    $hienTai = null;

                    continue;
                }
                $kq[$t['loai']][] = ['tieu_de' => $t['tieu_de'], 'dong' => []];
                $hienTai = $t['loai'];

                continue;
            }
            if ($this->coNgayVaGia($m) || ($m['o'] && $this->laDongTieuDeGia($m['chu']))) {
                $kq['thuoc'][$i] = true;

                continue;
            }
            $chu = ($m['gach'] ? '• ' : '').($m['o'] ? implode(' ', array_filter($m['o'])) : $m['chu']);
            if ($m['o'] && preg_match(self::GACH, $chu)) {
                $chu = '• '.trim(preg_replace(self::GACH, '', $chu)); // hàng bảng bắt đầu bằng "✓", "➢"…
            }
            if ($hienTai) {
                $kq['thuoc'][$i] = true;
                $dong = &$kq[$hienTai][count($kq[$hienTai]) - 1]['dong'];
                // Dòng bắt đầu bằng chữ thường / ngoặc là phần bị xuống dòng của mục trước
                // ("…(bao gồm 10 kg hành lý" + "xách tay và 23kg hành lý ký gửi)")
                if (! $m['gach'] && ! $m['o'] && $dong && preg_match('/^[\p{Ll}(]/u', $chu)
                    && ! preg_match('/[.!?]["”»)]?$/u', $dong[count($dong) - 1])) {
                    $dong[count($dong) - 1] .= ' '.$chu;
                } else {
                    $dong[] = $chu;
                }
                unset($dong);
            } elseif ($sauLichTrinh) {
                $kq['thuoc'][$i] = true;
                $kq['chung'][] = ltrim(preg_replace('/^(?:[•*]+\s*)?(?:lưu ý|ghi chú)?\s*:?\s*/iu', '', $chu));
            }
        }

        return $kq;
    }

    // ===================================================================
    // Bảng giá
    // ===================================================================

    private function laDongTieuDeGia(string $chu): bool
    {
        $k = self::khongDau($chu);

        return (bool) preg_match('/NGUOI LON|TRE EM|EM BE|GIA TOUR|NGAY KHOI HANH|THOI GIAN/', $k);
    }

    private function coNgayVaGia(array $m): bool
    {
        return $m['loai'] === 'chu' && $this->tienTrong($m['chu']) && $this->ngayTrong($this->phanNgay($m)) !== [];
    }

    /** @return list<int> các số tiền ≥ 500.000 theo thứ tự */
    private function tienTrong(string $s): array
    {
        preg_match_all('/(?<![\d.,])(\d{1,3}(?:[.,]\d{3}){2,3})(?![\d])/', $s, $k);

        return array_values(array_filter(array_map(fn ($x) => (int) preg_replace('/\D/', '', $x), $k[1]), fn ($v) => $v >= 500_000));
    }

    /**
     * Các số tiền của một hàng giá. Hàng bảng: chỉ lấy ô chỉ có số tiền — câu
     * "Phụ thu phòng đơn (nếu có): 5.990.000 VNĐ/ 01 hành trình" nằm sát dưới
     * bảng bị PDF gắn vào hàng giá thì không được thành giá người lớn.
     *
     * @return list<int>
     */
    private function tienHang(array $m): array
    {
        if (! $m['o']) {
            return $this->tienTrong($m['chu']);
        }
        $ra = [];
        foreach ($m['o'] as $o) {
            $tien = $this->tienTrong($o);
            $chu = preg_replace(['/(?<![\d.,])\d{1,3}(?:[.,]\d{3}){1,3}(?!\d)/', '/\b(vn[dđ]|vnd|đồng|đ)\b|\/\s*(khách|người|pax|tour|khach|nguoi)\b/iu'], '', $o);
            if ($tien && mb_strlen(preg_replace('/[^\p{L}]/u', '', $chu)) <= 3) {
                array_push($ra, ...$tien);
            }
        }

        return $ra;
    }

    /** Phần chữ chứa ngày của một hàng giá: ô đầu tiên có ngày, hoặc đoạn trước số tiền đầu tiên. */
    private function phanNgay(array $m): string
    {
        if ($m['o']) {
            foreach ($m['o'] as $j => $o) {
                if ($this->tienTrong($o)) {
                    break;
                }
                if (preg_match('/\d{1,2}\s*\/\s*\d{1,2}|tháng\s*\d/iu', $o)) {
                    // PDF cắt ô ngày sang ô bên cạnh: "Tháng 3/2027: 17, 24," | "31"
                    // (chỉ nối ô toàn số ngày rời — ô ghi chú "14/6/2026" bên cạnh thì không)
                    for ($k = $j + 1; isset($m['o'][$k]) && preg_match('/^\s*\d{1,2}(?:\s*[,;&]\s*\d{1,2})*\s*[,;]?\s*$/u', $m['o'][$k]); $k++) {
                        $o .= ' '.$m['o'][$k];
                    }

                    return $o;
                }
            }

            return '';
        }
        $vt = preg_match('/\d{1,3}(?:[.,]\d{3}){2,3}/', $m['chu'], $k, PREG_OFFSET_CAPTURE) ? $k[0][1] : strlen($m['chu']);

        return substr($m['chu'], 0, $vt);
    }

    /**
     * Các ngày trong ô ngày: "17/10", "24/06 – 28/06" (lấy ngày đi), "07/07 & 04/08",
     * "Tháng 10: 17, 21, 24", "Tháng 3/2027: 17, 24", "30/12 – 03/01/2027".
     * Bỏ phần trong ngoặc (ghi chú lễ).
     *
     * @return list<array{0:int,1:int,2:?int}> [ngày, tháng, năm|null]
     */
    private function ngayTrong(string $s): array
    {
        $s = preg_replace(['/\([^()]*\)/u', '/\b(lễ|tết|lễ hội)\s*\d{1,2}\s*\/\s*\d{1,2}/iu'], ' ', $s);
        if (preg_match('/tháng\s*0?(\d{1,2})(?:\s*\/\s*(\d{4}))?\s*:?(.*)$/isu', $s, $k) && ! preg_match('/\d{1,2}\s*\/\s*\d{1,2}/', $k[3])) {
            $thang = (int) $k[1];
            $nam = $k[2] !== '' ? (int) $k[2] : null;
            preg_match_all('/(?<!\d)(\d{1,2})(?!\d)/', $k[3], $so);

            return array_values(array_map(fn ($d) => [(int) $d, $thang, $nam], array_filter($so[1], fn ($d) => $d >= 1 && $d <= 31)));
        }
        preg_match_all('/(?<!\d)(\d{1,2})\s*\/\s*(\d{1,2})(?:\s*\/\s*(\d{2,4}))?(?!\d)/', $s, $k, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        $ra = [];
        $hetTruoc = null;
        foreach ($k as $x) {
            $d = (int) $x[1][0];
            $t = (int) $x[2][0];
            $nam = isset($x[3]) && $x[3][0] !== '' ? (int) $x[3][0] : null;
            if ($nam !== null && $nam < 100) {
                $nam += 2000;
            }
            // "24/06 – 28/06": ngày sau gạch nối là ngày VỀ, bỏ
            $giua = $hetTruoc !== null ? substr($s, $hetTruoc, $x[0][1] - $hetTruoc) : null;
            $hetTruoc = $x[0][1] + strlen($x[0][0]);
            if ($giua !== null && preg_match('/^\s*[–\-~]\s*$/u', $giua)) {
                // năm ghi ở ngày về ("30/12 – 03/01/2027") vẫn giúp suy ra năm ngày đi
                if ($nam && $ra && $ra[count($ra) - 1][2] === null) {
                    $ra[count($ra) - 1][2] = $t < $ra[count($ra) - 1][1] ? $nam - 1 : $nam;
                }

                continue;
            }
            if ($d >= 1 && $d <= 31 && $t >= 1 && $t <= 12) {
                $ra[] = [$d, $t, $nam];
            }
        }

        return $ra;
    }

    /** Thứ tự cột tiền theo dòng tiêu đề bảng giá (người lớn / trẻ em / em bé / phụ thu phòng đơn) */
    private function thuTuCot(string $tieuDe): array
    {
        $k = self::khongDau($tieuDe);
        $vt = [];
        foreach ([
            'nguoi_lon' => '/NGUOI LON/',
            'tre_em' => '/TRE EM/',
            'em_be' => '/EM BE|DUOI 0?2 ?(T\b|TUOI)/',
            'phong_don' => '/PHONG DON|PHU THU/',
        ] as $cot => $re) {
            if (preg_match($re, $k, $x, PREG_OFFSET_CAPTURE)) {
                $vt[$cot] = $x[0][1];
            }
        }
        asort($vt);

        return $vt ? array_keys($vt) : ['nguoi_lon', 'tre_em', 'em_be'];
    }

    /** @return array{0: list<array>, 1: list<string>, 2: array, 3: list<string>} [ngày còn đi được, ngày đã qua, phụ thu, chuyến bay] */
    private function bangGia(string $toanVan): array
    {
        $namChung = $this->namThamChieu($toanVan);
        $cot = ['nguoi_lon', 'tre_em', 'em_be'];
        $tieuDeGia = '';
        $dot = [];
        $boQua = [];
        $chuyenBay = [];
        $phongDon = [];
        $hangGia = [];
        $this->giaCu = [];
        $homNay = today();

        $dongTieuDe = null;
        foreach ($this->ds as $idx => $m) {
            if ($m['loai'] !== 'chu') {
                continue;
            }
            if ($this->laDongTieuDeGia($m['chu']) && ! $this->tienTrong($m['chu'])) {
                // Tiêu đề bảng có thể trải 2–3 hàng liền nhau (GIÁ TOUR / NGƯỜI LỚN | TRẺ EM | EM BÉ).
                // Dòng ở xa (VD "Quy định trẻ em: Dưới 02 tuổi…" phía trên) không cộng dồn vào
                // tiêu đề bảng — nếu không thứ tự cột người lớn / trẻ em / em bé bị đảo.
                $coNguoiLon = str_contains(self::khongDau($m['chu']), 'NGUOI LON');
                $lienTiep = $dongTieuDe !== null && $idx - $dongTieuDe <= 3;
                $tieuDeGia = $lienTiep && ! ($coNguoiLon && str_contains(self::khongDau($tieuDeGia), 'NGUOI LON'))
                    ? $tieuDeGia.' '.$m['chu'] : $m['chu'];
                $dongTieuDe = $idx;
                if (str_contains(self::khongDau($tieuDeGia), 'NGUOI LON') && count($thuTu = $this->thuTuCot($tieuDeGia)) >= 2) {
                    $cot = $thuTu;
                }

                continue;
            }
            $tien = $this->tienHang($m);
            $ngay = $tien ? $this->ngayTrong($this->phanNgay($m)) : [];
            if (! $tien || ! $ngay) {
                continue;
            }
            $tieuDeGia = '';
            $gia = [];
            foreach ($cot as $j => $c) {
                if (isset($tien[$j])) {
                    $gia[$c] = $tien[$j];
                }
            }
            // Chuyến bay ghi trong bảng giá (VD: "9G634 SGN – PVG 23:40 – 04:40")
            foreach ($m['o'] ?? [$m['chu']] as $o) {
                foreach (preg_split('/\n|\s*(?:=\s*){2,}/u', $o) as $doan) {
                    if (preg_match('/\b[A-Z0-9]{2}\s?\d{3,4}\b.*\b[A-Z]{3}\b/u', $doan) && ! $this->tienTrong($doan)) {
                        $chuyenBay[] = trim($doan, " \t=|");
                    }
                }
            }

            $hangGia[] = ['gia' => $gia, 'ngay' => $ngay];
        }

        // Năm của từng ngày (các ngày không ghi năm suy theo ngày có ghi năm)
        $tatCa = array_merge(...array_map(fn ($h) => $h['ngay'], $hangGia ?: [['ngay' => []]]));
        $nam = $this->ganNam($tatCa, $namChung);
        $stt = 0;
        foreach ($hangGia as ['gia' => $gia, 'ngay' => $ngay]) {
            foreach ($ngay as [$d, $t]) {
                $namDi = $nam[$stt++];
                if (! checkdate($t, $d, $namDi)) {
                    continue;
                }
                $ngayDi = Carbon::create($namDi, $t, $d)->startOfDay();
                $this->giaCu[] = $gia['nguoi_lon'] ?? null;
                if ($ngayDi->lt($homNay)) {
                    $boQua[] = $ngayDi->format('d/m/Y');

                    continue;
                }
                if (isset($phongDon[$ngayDi->toDateString()]) || collect($dot)->contains('ngay', $ngayDi->toDateString())) {
                    continue;
                }
                if (isset($gia['phong_don'])) {
                    $phongDon[$ngayDi->toDateString()] = $gia['phong_don'];
                }
                $dot[] = ['ngay' => $ngayDi->toDateString(), 'gia' => array_intersect_key($gia, array_flip(['nguoi_lon', 'tre_em', 'em_be']))];
            }
        }
        usort($dot, fn ($a, $b) => strcmp($a['ngay'], $b['ngay']));

        return [$dot, array_values(array_unique($boQua)), ['phong_don' => $phongDon], array_values(array_unique($chuyenBay))];
    }

    /**
     * Năm cho từng ngày khởi hành (theo thứ tự trong bảng giá). Ngày có ghi
     * năm thì dùng năm đó; ngày không ghi năm:
     *  - đứng sau một ngày có năm: cùng năm, sang tháng nhỏ hơn hẳn (12 → 1) thì +1
     *  - đứng TRƯỚC ngày có năm đầu tiên: suy ngược ("Tháng 9 … Tháng 12" rồi
     *    "Tháng 3/2027" → tháng 9–12 là năm 2026)
     *  - cả bảng không ghi năm: năm gợi ý từ file (xem namThamChieu); nếu với năm
     *    đó MỌI ngày đều đã qua thì là lịch của năm sau ("05/01, 12/01" nhập vào
     *    tháng 10 → tháng 1 năm sau)
     *
     * @param  list<array{0:int,1:int,2:?int}>  $ds
     * @return list<int>
     */
    private function ganNam(array $ds, int $namChung): array
    {
        $ra = array_map(fn ($x) => $x[2] ?? null, $ds);
        $dau = null;
        foreach ($ra as $k => $n) {
            if ($n !== null) {
                $dau = $k;
                break;
            }
        }
        $nam = $dau === null ? $namChung : $ra[$dau];
        $thangTruoc = null;
        for ($k = $dau ?? 0; $k < count($ds); $k++) {
            if ($ra[$k] !== null) {
                $nam = $ra[$k];
            } elseif ($thangTruoc !== null && $ds[$k][1] < $thangTruoc - 5) {
                $nam++; // sang năm mới trong bảng (tháng 12 → tháng 1)
            }
            $ra[$k] = $nam;
            $thangTruoc = $ds[$k][1];
        }
        if ($dau === null && $ds) {
            for ($lan = 0; $lan < 2 && $this->deuDaQua($ds, $ra); $lan++) {
                $ra = array_map(fn ($n) => $n + 1, $ra);
            }
            if ($lan > 0) {
                $this->canhBao[] = 'Bảng giá không ghi năm, các ngày trong năm '.($ra[0] - $lan).' đều đã qua — đã hiểu là năm '
                    .$ra[0].($ra[0] !== end($ra) ? '–'.end($ra) : '').'. Kiểm tra lại ngày khởi hành.';
            }
        }
        if ($dau) {
            $nam = $ra[$dau];
            $thangSau = $ds[$dau][1];
            for ($k = $dau - 1; $k >= 0; $k--) {
                if ($ds[$k][1] > $thangSau + 5) {
                    $nam--; // lùi qua năm cũ (tháng 3 ← tháng 12)
                }
                $ra[$k] = $nam;
                $thangSau = $ds[$k][1];
            }
        }

        return $ra;
    }

    /** Mọi ngày (với năm đã gán) đều trước hôm nay */
    private function deuDaQua(array $ds, array $nam): bool
    {
        foreach ($ds as $k => [$d, $t]) {
            if (checkdate($t, $d, $nam[$k]) && ! Carbon::create($nam[$k], $t, $d)->startOfDay()->lt(today())) {
                return false;
            }
        }

        return true;
    }

    /** @var list<?int> giá người lớn của mọi hàng (kể cả ngày đã qua) */
    private array $giaCu = [];

    /**
     * Năm dùng cho các ngày không ghi năm: năm xuất hiện nhiều nhất trong file
     * (bằng nhau thì năm gặp trước), không có thì năm nay.
     *
     * Chỉ tính năm trong khoảng năm ngoái → 2 năm tới: file hay nhắc năm cũ
     * ("Nghị định … 2024", "cập nhật 2024") — lấy năm đó thì cả bảng giá bị
     * gán năm 2024, mọi ngày khởi hành thành "đã qua".
     */
    private function namThamChieu(string $toanVan): int
    {
        $namNay = (int) today()->year;
        preg_match_all('/(?<!\d)(20[2-4]\d)(?!\d)/', $toanVan, $k);
        $k[1] = array_values(array_filter($k[1], fn ($n) => (int) $n >= $namNay - 1 && (int) $n <= $namNay + 2));
        if (! $k[1]) {
            return $namNay;
        }
        $dem = array_count_values($k[1]);
        $max = max($dem);
        foreach ($k[1] as $nam) {
            if ($dem[$nam] === $max) {
                return (int) $nam;
            }
        }

        return (int) today()->year;
    }

    /** Lưu ý "Bảng giá theo ngày" khi giá trẻ em / em bé / phụ thu phòng đơn khác nhau giữa các ngày. */
    private function bangGiaChiTiet(array $dot, array $giaPhu): ?string
    {
        $khac = fn (string $cot) => collect($dot)->pluck('gia.'.$cot)->filter()->unique()->count() > 1;
        if (! $dot || (! $khac('tre_em') && ! $khac('em_be') && ! $giaPhu['phong_don'])) {
            return null;
        }
        $vnd = fn (?int $v) => $v ? number_format($v, 0, ',', '.').'đ' : null;

        // Các ngày cùng mức giá gom một dòng: "17/10, 21/10, 24/10: người lớn … · trẻ em … · em bé …"
        return collect($dot)->groupBy(fn ($d) => implode(' · ', array_filter([
            'người lớn '.$vnd($d['gia']['nguoi_lon'] ?? null),
            isset($d['gia']['tre_em']) ? 'trẻ em '.$vnd($d['gia']['tre_em']) : null,
            isset($d['gia']['em_be']) ? 'em bé '.$vnd($d['gia']['em_be']) : null,
            isset($giaPhu['phong_don'][$d['ngay']]) ? 'phụ thu phòng đơn '.$vnd($giaPhu['phong_don'][$d['ngay']]) : null,
        ])))->map(fn ($ds, $gia) => '• '.$ds->map(fn ($d) => Carbon::parse($d['ngay'])->format('d/m'))->implode(', ').': '.$gia)
            ->implode("\n");
    }

    /** "Trẻ em dưới 02 tuổi: thu phí 30%…", "Từ 02 - dưới 10 tuổi: 90% giá của người lớn" */
    private function phanTramTreEm(string $toanVan): array
    {
        $kq = ['tre_em' => null, 'em_be' => null];
        foreach (preg_split('/\n/', $toanVan) as $dong) {
            if (! preg_match('/(\d{2,3})\s*%/u', $dong, $p) || ! preg_match('/tuổi|trẻ|em bé/iu', $dong)) {
                continue;
            }
            $k = self::khongDau($dong);
            if (preg_match('/DUOI 0?2 ?TUOI|EM BE/', $k) && ! preg_match('/TU 0?2/', $k)) {
                $kq['em_be'] ??= (int) $p[1];
            } elseif (preg_match('/TU 0?2|TRE EM/', $k) && (int) $p[1] < 100) {
                $kq['tre_em'] ??= (int) $p[1];
            }
        }

        return $kq;
    }

    // ===================================================================
    // Phần đầu: tên, thời gian, nơi khởi hành, nước đến, hạn visa
    // ===================================================================

    /** @return array{ten: ?string, goc: ?string} */
    private function ten(array $dau, string $tenFile, array $lichTrinh = []): array
    {
        $dong = [];
        foreach ($dau as $m) {
            // Tên tour nằm trên phần lịch trình: từ tiêu đề "LỊCH TRÌNH…" trở xuống là bảng
            // lịch trình rút gọn ("HÀ NỘI - SEOUL" của ngày 1), không phải tên tour
            if ($m['loai'] === 'chu' && preg_match('/^LICH TRINH\b/', self::khongDau($m['o'][0] ?? $m['chu']))) {
                break;
            }
            if ($m['loai'] === 'chu' && ! $m['gach'] && ! $m['o']) {
                $dong[] = $m;
            }
        }
        $laKhauHieu = fn (string $s) => (bool) preg_match('/^(khám phá|hành trình|trải nghiệm|chương trình|du lịch|tour)\b|:/iu', $s);
        $laHoa = fn (string $s) => mb_strtoupper($s) === $s && preg_match('/\p{L}/u', $s);
        foreach ($dong as $k => $m) {
            $chu = $m['chu'];
            // (bỏ dòng lịch chạy "Thứ 4 – Thứ 7 hàng tuần", "5 ngày 4 đêm")
            if (mb_strlen($chu) > 90 || $laKhauHieu($chu) || ! preg_match('/\s[–\-]\s/u', $chu) || ! ($laHoa($chu) || $m['dam'])
                || preg_match('/\bthứ\s*[2-7]\b|chủ nhật|h[àằ]ng tuần|\d+\s*(ngày|đêm)\b/iu', $chu)) {
                continue;
            }
            // Dòng ngay trên là tên nước / thành phố ngắn ("BẮC KINH", "VƯƠNG QUỐC BHUTAN") → ghép vào trước
            $truoc = $dong[$k - 1]['chu'] ?? null;
            $ten = $chu;
            if ($truoc && mb_strlen($truoc) <= 30 && $laHoa($truoc) && ! $laKhauHieu($truoc) && ! preg_match('/\s[–\-]\s/u', $truoc)) {
                $ten = preg_replace('/^VƯƠNG QUỐC\s+/u', '', $truoc).' – '.$chu;
            }

            return ['ten' => $this->hoaDau(preg_replace('/\s*[–\-]\s*/u', ' – ', $ten)), 'goc' => $chu];
        }
        // Không thấy dòng hành trình: ghép từ các chặng trong lịch trình — tên file
        // của đối tác hay kèm mã nội bộ ("NLG MU - NO SHOP HGH.PVG D1…")
        if ($tuLichTrinh = $this->tenTuLichTrinh($lichTrinh)) {
            $this->canhBao[] = 'Không thấy dòng tên tour trong file — tạm ghép tên từ các chặng trong lịch trình, sửa lại cho đúng.';

            return ['ten' => $tuLichTrinh, 'goc' => null];
        }
        // Không có cả lịch trình: lấy tên file ("THƯỢNG HẢI - HÀNG CHÂU 5N4D.docx")
        $tep = preg_replace(['/\.(docx|pdf)$/i', '/[_]+/', '/\b\d+\s*N\s*\d+\s*[DĐ]\b/iu'], ['', ' ', ''], $tenFile);
        $this->canhBao[] = 'Không nhận ra tên tour trong file — tạm lấy theo tên file, sửa lại cho đúng.';

        return ['ten' => trim($this->hoaDau(trim($tep))) ?: null, 'goc' => null];
    }

    /**
     * "TP.HCM – Hàng Châu", "Hàng Châu – Tây Sách Ô Trấn", "Thượng Hải City Tour",
     * "Thượng Hải – TP. HCM" → "Hàng Châu – Tây Sách Ô Trấn – Thượng Hải".
     * Bỏ thành phố xuất phát, ngày cuối (chặng về), chữ "city tour / tham quan…".
     */
    private function tenTuLichTrinh(array $lichTrinh): ?string
    {
        if (count($lichTrinh) < 2) {
            return null;
        }
        $noiDi = ['HO CHI MINH', 'TP HCM', 'HCM', 'SAI GON', 'HA NOI', 'DA NANG', 'CAN THO', 'HAI PHONG', 'NHA TRANG', 'PHU QUOC'];
        $diem = [];
        foreach (array_slice($lichTrinh, 0, -1) as $n) {
            foreach (preg_split('/\s*–\s*|\s+-\s*|\s*-\s+/u', $n['tieu_de']) as $p) {
                $p = trim(preg_replace('/\b(city\s*tour|tham quan|trải nghiệm|tự do|khám phá)\b/iu', '', $p), " \t.,:");
                $k = self::khongDau(preg_replace('/[^\p{L}\s]/u', ' ', $p));
                $k = trim(preg_replace('/\s+/', ' ', $k));
                if ($p === '' || mb_strlen($p) > 40 || collect($noiDi)->contains(fn ($x) => str_contains(' '.$k.' ', ' '.$x.' '))) {
                    continue;
                }
                $diem[$k] ??= $this->hoaDau($p);
            }
        }

        return count($diem) >= 2 ? implode(' – ', array_slice(array_values($diem), 0, 5)) : null;
    }

    /** @return array{0: ?int, 1: ?int} */
    private function thoiGian(array $dau, string $tenFile, int $soNgayLichTrinh): array
    {
        $chu = implode("\n", array_map(fn ($m) => $m['chu'] ?? '', $dau));
        if (preg_match('/(\d{1,2})\s*ngày\s*[\/,\-–]?\s*(\d{1,2})\s*đêm/iu', $chu, $k)) {
            return [(int) $k[1], (int) $k[2]];
        }
        if (preg_match('/(?<![A-Z0-9])(\d{1,2})\s*N\s*(\d{1,2})\s*[DĐ](?![A-Z])/iu', $tenFile, $k)) {
            return [(int) $k[1], (int) $k[2]];
        }

        return $soNgayLichTrinh ? [$soNgayLichTrinh, max(0, $soNgayLichTrinh - 1)] : [null, null];
    }

    private function noiKhoiHanh(array $lichTrinh): ?string
    {
        $dau = self::khongDau(explode('–', $lichTrinh[0]['tieu_de'] ?? '')[0]);
        foreach (['HO CHI MINH' => 'Hồ Chí Minh', 'HCM' => 'Hồ Chí Minh', 'SAI GON' => 'Hồ Chí Minh', 'HA NOI' => 'Hà Nội', 'DA NANG' => 'Đà Nẵng',
            'CAN THO' => 'Cần Thơ', 'HAI PHONG' => 'Hải Phòng', 'NHA TRANG' => 'Nha Trang', 'PHU QUOC' => 'Phú Quốc'] as $khoa => $ten) {
            if (str_contains($dau, $khoa)) {
                return $ten;
            }
        }

        return null;
    }

    private function nuoc(string $toanVan): ?string
    {
        $k = ' '.preg_replace('/[^A-Z0-9]+/', ' ', self::khongDau($toanVan)).' ';
        $dem = [];
        foreach (self::NUOC as $ten => $cach) {
            foreach ($cach as $c) {
                $dem[$ten] = ($dem[$ten] ?? 0) + substr_count($k, ' '.$c.' ');
            }
        }
        arsort($dem);
        $ten = array_key_first($dem);

        return $ten && $dem[$ten] > 0 ? $ten : null;
    }

    /** "cung cấp hồ sơ xin visa trước ngày khởi hành ít nhất 15 ngày" → 15 */
    private function hanVisa(string $toanVan): ?int
    {
        foreach (preg_split('/\n|(?<=[.;])\s/u', $toanVan) as $cau) {
            if (! preg_match('/visa|thị thực|hồ sơ/iu', $cau)) {
                continue;
            }
            if (preg_match('/trước\s*(?:ngày\s*khởi\s*hành\s*)?(?:ít\s*nhất\s*|tối\s*thiểu\s*)?(\d{1,3})\s*ngày/iu', $cau, $k)
                && (int) $k[1] >= 3 && (int) $k[1] <= 120) {
                return (int) $k[1];
            }
        }

        return null;
    }

    // ===================================================================
    // Ảnh
    // ===================================================================

    /** Bỏ ảnh trùng, ảnh quá nhỏ (biểu tượng, gạch đầu dòng), ảnh không đọc được. */
    private function locAnh(array $anh): array
    {
        $daCo = [];
        $ra = [];
        $nho = 0;
        foreach ($anh as $a) {
            $kt = @getimagesizefromstring($a['du_lieu']);
            if (! $kt || ! in_array($kt[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP, IMAGETYPE_BMP], true)) {
                continue;
            }
            [$rong, $cao] = $kt;
            $md5 = md5($a['du_lieu']);
            if (isset($daCo[$md5]) || $rong < 150 || $cao < 100) {
                continue;
            }
            $daCo[$md5] = true;
            $nho += $rong < 600 ? 1 : 0;
            $ra[] = ['du_lieu' => $a['du_lieu'], 'rong' => $rong, 'cao' => $cao, 'ngay' => $a['ngay']];
        }
        if ($ra && $nho === count($ra)) {
            $this->canhBao[] = 'Ảnh trong file đều nhỏ (dưới 600px) — ngoài web sẽ hơi mờ, nên thay ảnh bìa đẹp hơn.';
        }

        return $ra;
    }
}
