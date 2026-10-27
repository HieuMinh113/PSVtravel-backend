<?php

namespace Modules\Tour\Services\NhapTour;

use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Đọc file chương trình tour .pdf thành dãy "mục" giống DocWord.
 *
 * Dùng `pdftohtml -xml` (bộ poppler-utils, cài trong ảnh Docker app): ra từng
 * dòng chữ có toạ độ + in đậm, và ảnh có toạ độ trên trang. Nhờ toạ độ mới
 * dựng lại đúng thứ tự đọc và gắn ảnh vào đúng ngày trong lịch trình — các
 * thư viện PHP đọc PDF đã thử đều đảo lộn thứ tự tiêu đề ngày và không biết
 * ảnh nằm ở trang nào.
 */
class DocPdf
{
    /** @var list<array> mẩu chữ có toạ độ của file vừa đọc */
    private array $manhChu = [];

    /** @return list<array> */
    public function doc(string $duong): array
    {
        $thuMuc = storage_path('app/private/nhap-tour/'.Str::random(16));
        File::ensureDirectoryExists($thuMuc);

        try {
            $kq = Process::timeout(90)->run(['pdftohtml', '-xml', '-q', '-nodrm', '-zoom', '1', '-fontfullname', $duong, $thuMuc.'/p']);
            if (! $kq->successful() || ! is_file($thuMuc.'/p.xml')) {
                if (str_contains($kq->errorOutput().$kq->output(), 'not found') || $kq->exitCode() === 127) {
                    throw new RuntimeException('Máy chủ chưa có công cụ đọc PDF (poppler-utils). Chạy: docker compose build app && docker compose up -d');
                }
                throw new RuntimeException('Không đọc được file PDF (file hỏng hoặc có mật khẩu).');
            }

            return $this->tuXml((string) file_get_contents($thuMuc.'/p.xml'), $thuMuc);
        } finally {
            File::deleteDirectory($thuMuc);
        }
    }

    /** @return list<array> */
    private function tuXml(string $xml, string $thuMuc): array
    {
        $dom = new DOMDocument;
        $dom->loadXML($xml, LIBXML_NONET | LIBXML_RECOVER | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_PARSEHUGE);

        $phanTu = []; // [trang, top, left, ...]
        $anhNho = []; // ảnh gạch đầu dòng (nhỏ) theo trang
        $demAnh = []; // md5 ảnh → số trang có ảnh đó (ảnh lặp lại mỗi trang = logo đầu trang)
        foreach ($dom->getElementsByTagName('page') as $trang) {
            $so = (int) $trang->getAttribute('number');
            foreach ($trang->childNodes as $n) {
                if (! $n instanceof DOMElement) {
                    continue;
                }
                $top = (float) $n->getAttribute('top');
                $left = (float) $n->getAttribute('left');
                $rong = (float) $n->getAttribute('width');
                $cao = (float) $n->getAttribute('height');
                if ($n->tagName === 'text') {
                    // mb_scrub: phông lạ có thể cho ra byte không phải UTF-8 — các hàm preg_*
                    // gặp chuỗi hỏng sẽ trả null, mất chữ hoặc làm hỏng cả tour
                    $tho = mb_scrub(html_entity_decode(strip_tags($dom->saveXML($n)), ENT_QUOTES | ENT_HTML5), 'UTF-8');
                    $chu = trim($tho);
                    if ($chu === '') {
                        continue;
                    }
                    $dam = $n->getElementsByTagName('b')->length > 0;
                    $phanTu[] = ['loai' => 'chu', 'trang' => $so, 'top' => $top, 'left' => $left, 'rong' => $rong, 'cao' => $cao, 'chu' => $chu, 'dam' => $dam,
                        // dấu cách ở mép mẩu chữ — để biết hai mẩu sát nhau là một chữ bị cắt hay hai chữ
                        'cach_dau' => (bool) preg_match('/^\s/u', $tho), 'cach_cuoi' => (bool) preg_match('/\s$/u', $tho)];
                } elseif ($n->tagName === 'image') {
                    if ($rong <= 24 && $cao <= 24) {
                        $anhNho[$so][] = $top + $cao / 2; // gạch đầu dòng dạng ảnh

                        continue;
                    }
                    $tep = $thuMuc.'/'.basename($n->getAttribute('src'));
                    $duLieu = is_file($tep) ? (string) file_get_contents($tep) : '';
                    if ($duLieu === '') {
                        continue;
                    }
                    $md5 = md5($duLieu);
                    $demAnh[$md5][$so] = true;
                    $moi = ['loai' => 'anh', 'trang' => $so, 'top' => $top, 'left' => $left, 'rong' => $rong, 'cao' => $cao, 'du_lieu' => $duLieu, 'ten' => basename($tep), 'md5' => $md5];
                    // Cùng một ảnh đôi khi được xuất hai lần chồng lên nhau (ảnh + bóng đổ) → giữ ảnh nét hơn
                    foreach ($phanTu as $k => $cu) {
                        if ($cu['loai'] === 'anh' && $cu['trang'] === $so && self::chong($cu, $moi) > 0.5) {
                            if (strlen($duLieu) > strlen($cu['du_lieu'])) {
                                $phanTu[$k] = $moi;
                            }
                            $moi = null;
                            break;
                        }
                    }
                    if ($moi) {
                        $phanTu[] = $moi;
                    }
                }
            }
        }

        // Thứ tự đọc: trang → từ trên xuống → trái sang phải
        usort($phanTu, fn ($a, $b) => [$a['trang'], $a['top'], $a['left']] <=> [$b['trang'], $b['top'], $b['left']]);
        $phanTu = $this->ghepChuBiCat($phanTu);
        $this->manhChu = array_values(array_filter($phanTu, fn ($p) => $p['loai'] === 'chu'));
        $phanTu = $this->bangGia($phanTu);

        // Gom các mẩu chữ cùng một hàng (lệch nhau vài điểm) thành một dòng
        $dong = [];
        foreach ($phanTu as $p) {
            if ($p['loai'] === 'anh') {
                if (count($demAnh[$p['md5']]) >= 3) {
                    continue; // logo / băng rôn lặp trên mọi trang
                }
                $dong[] = $p;

                continue;
            }
            if ($p['loai'] === 'hang') {
                $dong[] = $p;

                continue;
            }
            $cuoi = $dong ? array_key_last($dong) : null;
            if ($cuoi !== null && $dong[$cuoi]['loai'] === 'chu' && $dong[$cuoi]['trang'] === $p['trang']
                && abs($dong[$cuoi]['top'] - $p['top']) <= max(3, min($dong[$cuoi]['cao'], $p['cao']) * 0.4)) {
                $dong[$cuoi]['manh'][] = $p;

                continue;
            }
            $dong[] = $p + ['manh' => [$p]];
        }

        $muc = [];
        foreach ($dong as $d) {
            if ($d['loai'] === 'hang') {
                $muc[] = ['loai' => 'chu', 'chu' => implode(' | ', $d['o']), 'dam' => false, 'gach' => false, 'o' => $d['o'],
                    'trang' => $d['trang'], 'top' => $d['top'], 'cao' => $d['cao'], 'left' => $d['left'], 'tren' => $d['top']];

                continue;
            }
            if ($d['loai'] === 'anh') {
                $muc[] = ['loai' => 'anh', 'du_lieu' => $d['du_lieu'], 'ten' => $d['ten'], 'trang' => $d['trang'],
                    'tren' => $d['top'], 'duoi' => $d['top'] + $d['cao']];

                continue;
            }
            usort($d['manh'], fn ($a, $b) => $a['left'] <=> $b['left']);
            // Gạch đầu dòng là một ký hiệu đứng riêng (phông Symbol / Wingdings) sát trước chữ
            $gachKyHieu = false;
            if (count($d['manh']) > 1 && preg_match('/^[\p{So}\x{F000}-\x{F0FF}•‣∙]$/u', $d['manh'][0]['chu'])
                && $d['manh'][1]['left'] - ($d['manh'][0]['left'] + $d['manh'][0]['rong']) <= 18 && preg_match('/\p{L}/u', $d['manh'][1]['chu'])) {
                array_shift($d['manh']);
                $gachKyHieu = true;
                $d['top'] = $d['manh'][0]['top']; // ký hiệu thường to hơn chữ — lấy cỡ của chữ để nối dòng
                $d['cao'] = $d['manh'][0]['cao'];
            }
            $o = [];
            $phai = null;
            foreach ($d['manh'] as $m) {
                // Khoảng trống lớn giữa hai mẩu = sang cột khác của bảng
                if ($phai !== null && $m['left'] - $phai > 18 && $o) {
                    $o[] = $m['chu'];
                } elseif ($o) {
                    $o[count($o) - 1] .= ' '.$m['chu'];
                } else {
                    $o[] = $m['chu'];
                }
                $phai = $m['left'] + $m['rong'];
            }
            $dam = array_sum(array_map(fn ($m) => $m['so_dam'] ?? (int) $m['dam'], $d['manh'])) * 2
                >= array_sum(array_map(fn ($m) => $m['so_manh'] ?? 1, $d['manh']));
            $giua = $d['top'] + $d['cao'] / 2;
            $gach = $gachKyHieu || collect($anhNho[$d['trang']] ?? [])->contains(fn ($y) => abs($y - $giua) <= max(6, $d['cao']));
            $muc[] = [
                'loai' => 'chu', 'chu' => implode(' | ', $o), 'dam' => $dam, 'gach' => $gach,
                'o' => count($o) > 1 ? $o : null,
                // để nối các dòng bị ngắt trong cùng một đoạn
                'trang' => $d['trang'], 'top' => $d['top'], 'cao' => $d['cao'], 'left' => $d['manh'][0]['left'], 'tren' => $d['top'],
                'phai' => max(array_map(fn ($m) => $m['left'] + $m['rong'], $d['manh'])),
            ];
        }

        return $this->noiDong($muc);
    }

    /**
     * PDF ngắt một đoạn văn thành nhiều dòng theo bề rộng trang — nối lại.
     * Dòng sau được nối vào dòng trước khi: cùng kiểu chữ, sát ngay bên dưới,
     * không phải gạch đầu dòng / mốc giờ / mốc buổi / tiêu đề, và dòng trước
     * chưa kết thúc câu.
     *
     * @param  list<array>  $muc
     * @return list<array>
     */
    private function noiDong(array $muc): array
    {
        $ra = [];
        foreach ($muc as $m) {
            $truoc = $ra ? $ra[array_key_last($ra)] : null;
            if ($m['loai'] === 'chu' && $truoc && $truoc['loai'] === 'chu'
                && ! $m['gach'] && $m['o'] === null && $truoc['o'] === null
                && $m['dam'] === $truoc['dam']
                && $m['trang'] === $truoc['trang']
                && abs($m['cao'] - $truoc['cao']) <= 1
                && self::hoaHet($m['chu']) === self::hoaHet($truoc['chu'])
                && $m['cao'] > $m['top'] - ($truoc['top'] + $truoc['cao'])
                // cùng một cột chữ (hai khung chữ đặt cạnh nhau thì không nối)
                && min($m['phai'], $truoc['phai']) > max($m['left'], $truoc['left'])
                && ! preg_match('/[.!?:;]["”»)]?$/u', $truoc['chu'])
                && ! preg_match(PhanTichChuongTrinh::MOC_DONG, $m['chu'])) {
                $ra[array_key_last($ra)]['chu'] .= ' '.$m['chu'];
                // giữ các dòng gốc: tên tour in to trên trang bìa xuống 2–3 dòng cũng bị nối
                $ra[array_key_last($ra)]['cac_dong'] = [...($truoc['cac_dong'] ?? [$truoc['chu']]), $m['chu']];
                $ra[array_key_last($ra)]['top'] = $m['top'];
                $ra[array_key_last($ra)]['cao'] = $m['cao'];
                $ra[array_key_last($ra)]['left'] = min($m['left'], $truoc['left']);
                $ra[array_key_last($ra)]['phai'] = max($m['phai'], $truoc['phai']);

                continue;
            }
            $ra[] = $m;
        }

        // tren / duoi: mép trên dòng đầu, mép dưới dòng cuối của mục — để bộ đọc khác biết mục nằm ở đâu trên trang
        return array_map(fn ($m) => array_diff_key($m + (isset($m['top'], $m['cao']) ? ['duoi' => $m['top'] + $m['cao']] : []),
            array_flip(['top', 'cao', 'left', 'phai'])), $ra);
    }

    /**
     * Word xuất PDF với hai phông (một phông cho chữ Latin, một cho chữ có dấu
     * tiếng Việt) thì một chữ bị cắt thành nhiều mẩu nằm sát nhau: "CH" +
     * "ƯƠNG TR" + "ÌNH", "03/" + "06" (hai màu). Ghép lại thành một mẩu, KHÔNG
     * chèn dấu cách — nếu không sẽ ra "CH ƯƠNG TR ÌNH", từ khoá "BAO GỒM",
     * "HỦY", "NGƯỜI LỚN" không còn nhận ra được.
     * Hai mẩu được ghép khi: cùng hàng (chồng nhau theo chiều dọc), mẩu sau bắt
     * đầu ngay chỗ mẩu trước kết thúc (hở ≤ 1,5 điểm) và không mẩu nào có dấu
     * cách ở chỗ nối.
     *
     * @param  list<array>  $phanTu  đã sắp theo thứ tự đọc
     * @return list<array>
     */
    private function ghepChuBiCat(array $phanTu): array
    {
        $ra = [];
        foreach ($phanTu as $p) {
            if ($p['loai'] === 'chu') {
                // tìm trong vài mẩu vừa xếp: mẩu cùng hàng có thể lệch top 1–2 điểm nên
                // không liền nhau, và mẩu bên phải có khi được xếp trước mẩu bên trái
                for ($k = count($ra) - 1; $k >= max(0, count($ra) - 40); $k--) {
                    $q = $ra[$k];
                    // cùng hàng: hai khung chồng nhau theo chiều dọc ≥ 60% (phông chữ có dấu
                    // thường cao hơn, lệch top 2–3 điểm nhưng cùng đường chân chữ)
                    $chongDoc = min($q['top'] + $q['cao'], $p['top'] + $p['cao']) - max($q['top'], $p['top']);
                    if ($q['loai'] !== 'chu' || $q['trang'] !== $p['trang'] || $chongDoc < 0.6 * min($q['cao'], $p['cao']) || abs($q['cao'] - $p['cao']) > 4) {
                        continue;
                    }
                    $sau = $p['left'] - ($q['left'] + $q['rong']); // p nằm ngay sau q
                    $truoc = $q['left'] - ($p['left'] + $p['rong']); // p nằm ngay trước q
                    if ($sau >= -2 && $sau <= 1.5 && ! $q['cach_cuoi'] && ! $p['cach_dau']) {
                        [$trai, $phai] = [$q, $p];
                    } elseif ($truoc >= -2 && $truoc <= 1.5 && ! $p['cach_cuoi'] && ! $q['cach_dau']) {
                        [$trai, $phai] = [$p, $q];
                    } else {
                        continue;
                    }
                    // giữ toạ độ / cỡ chữ của mẩu bên trái để việc nối dòng phía sau không đổi
                    $ra[$k] = array_merge($trai, [
                        'chu' => $trai['chu'].$phai['chu'],
                        'rong' => $phai['left'] + $phai['rong'] - $trai['left'],
                        'cach_cuoi' => $phai['cach_cuoi'],
                        // dòng in đậm hay không vẫn tính theo số mẩu GỐC (như trước khi ghép)
                        'so_manh' => ($p['so_manh'] ?? 1) + ($q['so_manh'] ?? 1),
                        'so_dam' => ($p['so_dam'] ?? (int) $p['dam']) + ($q['so_dam'] ?? (int) $q['dam']),
                        'dam' => $p['dam'] && $q['dam'],
                    ]);

                    continue 2;
                }
            }
            $ra[] = $p;
        }

        return $ra;
    }

    /**
     * Mẩu chữ có toạ độ (trang, top, left, rong, cao, chu, dam…) của file vừa
     * doc(), đã ghép chữ bị cắt — cho bộ đọc cần vị trí trên trang, như bảng giá
     * USD theo hạng phòng của combo Maldives.
     *
     * @return list<array>
     */
    public function manhChu(): array
    {
        return $this->manhChu;
    }

    /** Mẩu chữ là ô ngày khởi hành: "03/06", "05, 19/08", "Tháng 10: 17, 21" */
    private static function laNgayBang(string $s): bool
    {
        return (bool) preg_match('/^\s*(?:tháng\s*\d|\d{1,2}\s*\/\s*\d{1,2}|\d{1,2}(?:\s*[,&]\s*\d{1,2})+)/iu', $s);
    }

    /** Tiêu đề phần sau bảng giá, hoặc dòng đầu danh sách đánh số */
    private static function laTieuDePhan(string $s): bool
    {
        return (bool) preg_match('/BAO GOM|LUU Y|GHI CHU|DIEU KIEN|CHINH SACH|\bHUY\b|THU TUC|HO SO|QUY DINH/', strtoupper(Str::ascii($s)))
            || (bool) preg_match('/^\s*\d{1,2}\.\s+\p{L}/u', $s);
    }

    private static function hoaHet(string $s): bool
    {
        $chu = preg_replace('/[^\p{L}]/u', '', $s);

        return $chu !== '' && mb_strtoupper($chu) === $chu;
    }

    /** Tỉ lệ chồng lên nhau của hai khung (so với khung nhỏ hơn) */
    private static function chong(array $a, array $b): float
    {
        $x = max(0, min($a['left'] + $a['rong'], $b['left'] + $b['rong']) - max($a['left'], $b['left']));
        $y = max(0, min($a['top'] + $a['cao'], $b['top'] + $b['cao']) - max($a['top'], $b['top']));
        $nho = min($a['rong'] * $a['cao'], $b['rong'] * $b['cao']);

        return $nho > 0 ? $x * $y / $nho : 0;
    }

    /**
     * Số tiền nằm giữa một câu chữ ("…phụ thu phòng đơn: 4.500.000 VNĐ", "Phòng đơn
     * (khi có nhu cầu…): 4.500.000 VNĐ. Lễ 02/09 áp dụng cho ngày khởi hành 30/08
     * 5.500.000VNĐ") chứ không phải ô bảng giá: cùng mẩu có cả câu dài, hoặc ngay
     * bên trái, cùng dòng, có chữ sát liền (cách một dấu cách). Ô bảng giá luôn
     * cách ô bên trái một khoảng rộng.
     */
    private static function trongCau(array $p, array $phanTu, int $i): bool
    {
        // cả câu nằm chung một mẩu với số tiền: bỏ số tiền, đơn vị đi vẫn còn nhiều chữ
        $con = preg_replace(['/(?<![\d.,])\d{1,3}(?:[.,]\d{3}){1,3}(?!\d)/', '/\b(vn[dđ]|đồng|đ)\b/iu'], '', $p['chu']);
        if (preg_match_all('/\p{L}/u', $con) >= 20) {
            return true;
        }
        for ($k = max(0, $i - 40); $k <= min(count($phanTu) - 1, $i + 40); $k++) {
            $q = $phanTu[$k];
            if ($k === $i || $q['loai'] !== 'chu' || $q['trang'] !== $p['trang']) {
                continue;
            }
            $chongDoc = min($q['top'] + $q['cao'], $p['top'] + $p['cao']) - max($q['top'], $p['top']);
            $ho = $p['left'] - ($q['left'] + $q['rong']);
            if ($chongDoc >= 0.6 * min($q['cao'], $p['cao']) && $ho >= -2 && $ho <= 6
                && ! self::tien($q['chu']) && preg_match_all('/\p{L}/u', $q['chu']) >= 3) {
                return true;
            }
        }

        return false;
    }

    private const TIEN = '/(?<![\d.,])(\d{1,3}(?:[.,]\d{3}){2,3})(?![\d])/';

    /** @return list<string> các số tiền ≥ 500.000 trong mẩu chữ */
    private static function tien(string $s): array
    {
        preg_match_all(self::TIEN, $s, $k);

        return array_values(array_filter($k[1], fn ($x) => (int) preg_replace('/\D/', '', $x) >= 500_000));
    }

    /**
     * Dựng lại bảng giá. Trong PDF, chữ của một ô được căn giữa theo chiều dọc
     * nên ngày khởi hành ("Tháng 10:" / "17, 21, 24…") nằm lệch hàng với số
     * tiền, đọc từng dòng là lẫn hàng nọ sang hàng kia. Ở đây:
     *  - mỗi nhóm số tiền cùng độ cao = một hàng giá
     *  - các mẩu chữ bên trái cột tiền (ngày, chuyến bay) gắn vào hàng: theo
     *    nhãn "Tháng N" nếu có (nhãn thứ k ↔ hàng thứ k), không thì hàng gần nhất
     *  - phần tiêu đề bảng (NGƯỜI LỚN | TRẺ EM | EM BÉ…) gom thành một hàng
     * Kết quả là các hàng có ô như bảng của file Word.
     *
     * @param  list<array>  $phanTu  đã sắp theo thứ tự đọc
     * @return list<array>
     */
    private function bangGia(array $phanTu): array
    {
        // Các hàng giá: mẩu có số tiền, gom theo trang + độ cao
        $hang = [];
        foreach ($phanTu as $i => $p) {
            // "3.200.000/khách/trọn tour", "5.990.000 VNĐ/ 01 hành trình" là phụ thu trong câu, không phải ô bảng giá
            if ($p['loai'] !== 'chu' || ! self::tien($p['chu']) || preg_match('/\d{3}\s*(?:vn[dđ]|đ)?\s*\/\s*[\p{L}\d]/iu', $p['chu'])
                || self::trongCau($p, $phanTu, $i)) {
                continue;
            }
            foreach ($hang as $k => $h) {
                if ($h['trang'] === $p['trang'] && abs($h['top'] - $p['top']) <= 5) {
                    $hang[$k]['tien'][] = $i;
                    $hang[$k]['x0'] = min($h['x0'], $p['left']);

                    continue 2;
                }
            }
            $hang[] = ['trang' => $p['trang'], 'top' => $p['top'], 'cao' => $p['cao'], 'x0' => $p['left'], 'tien' => [$i]];
        }
        if (! $hang) {
            return $phanTu;
        }

        // Chia thành từng bảng: các hàng liền nhau (cùng trang cách ≤ 150, hoặc sang đầu trang sau).
        // Sang trang sau mà phía trên hàng đó đã có tiêu đề phần / mục đánh số ("GIÁ TOUR BAO
        // GỒM", "1. Vé máy bay…") thì không còn là bảng giá vắt trang nữa.
        $coTieuDePhanTren = fn (array $h) => collect($phanTu)->contains(fn ($p) => $p['loai'] === 'chu' && $p['trang'] === $h['trang']
            && $p['top'] < $h['top'] - 2 && ! self::tien($p['chu']) && self::laTieuDePhan($p['chu']));
        $bang = [];
        foreach ($hang as $h) {
            $cuoi = $bang ? $bang[count($bang) - 1] : null;
            $truoc = $cuoi ? $cuoi[count($cuoi) - 1] : null;
            $cungKhuon = count($h['tien']) === count($truoc['tien'] ?? []) || abs($h['x0'] - ($truoc['x0'] ?? -99)) <= 10;
            if ($truoc && $cungKhuon && (($h['trang'] === $truoc['trang'] && $h['top'] - $truoc['top'] <= 150)
                || ($h['trang'] === $truoc['trang'] + 1 && $h['top'] < 300 && ! $coTieuDePhanTren($h)))) {
                $bang[count($bang) - 1][] = $h;
            } else {
                $bang[] = [$h];
            }
        }

        $daDung = [];
        $them = [];
        foreach ($bang as $ds) {
            // Bảng chỉ có 1 hàng mà không có tiêu đề giá ở trên → không phải bảng giá (VD câu "phí visa 5.800.000")
            $x0 = min(array_column($ds, 'x0'));
            $dau = $ds[0];
            $tieuDe = [];
            foreach ($phanTu as $i => $p) {
                if ($p['loai'] === 'chu' && $p['trang'] === $dau['trang'] && $p['top'] < $dau['top'] - 2 && $p['top'] >= $dau['top'] - 140
                    && preg_match('/NGUOI LON|TRE EM|EM BE|KHOI HANH|THOI GIAN|GIA TOUR|CHUYEN BAY|TUOI|PHONG DON|PHU THU|GHI CHU|\bKIEN\b|VND|\bKHACH\b|NGU CHUNG|\(.*\d\s*T\b/u', strtoupper(Str::ascii($p['chu'])))) {
                    $tieuDe[$i] = $p;
                }
            }
            $tieuDe = array_filter($tieuDe, fn ($p) => ! str_contains(strtoupper(Str::ascii($p['chu'])), 'BAO GOM'));
            $tieuDeRo = collect($tieuDe)->contains(fn ($p) => preg_match('/NGUOI LON|KHOI HANH|GIA TOUR/', strtoupper(Str::ascii($p['chu']))));
            if (count($ds) === 1 && (! $tieuDe || (count($dau['tien']) === 1 && ! $tieuDeRo))) {
                continue;
            }
            $tuTren = $tieuDe ? min(array_column($tieuDe, 'top')) : $dau['top'] - 3;
            // Vùng tiêu đề kết thúc ở đáy các chữ tiêu đề, không kéo tới hàng giá đầu
            // tiên: ô giá gộp nhiều hàng ngày ("03/06, 03/07, 05/08 — 26.490.000")
            // thì số tiền nằm giữa nhóm, các ngày phía trên nó là thân bảng.
            $denDuoi = $tieuDe ? max(array_map(fn ($p) => $p['top'] + $p['cao'], $tieuDe)) : $dau['top'] - 2;
            foreach ($phanTu as $i => $p) {
                if ($p['loai'] === 'chu' && $p['trang'] === $dau['trang'] && $p['top'] >= $tuTren
                    && $p['top'] < min($dau['top'] - 2, $denDuoi + 2) && ! self::laNgayBang($p['chu'])) {
                    $tieuDe[$i] = $p; // mọi chữ trong vùng tiêu đề bảng
                }
            }

            // Vùng thân bảng, theo từng trang (bảng có thể vắt sang trang sau):
            // từ trên hàng đầu tới dưới hàng cuối của trang đó một khoảng ≈ chiều cao một hàng
            $cach = [];
            foreach ($ds as $k => $h) {
                if ($k > 0 && $ds[$k - 1]['trang'] === $h['trang']) {
                    $cach[] = $h['top'] - $ds[$k - 1]['top'];
                }
            }
            sort($cach);
            $khoang = $cach ? min(70, max(25, $cach[intdiv(count($cach), 2)])) : 45;
            $vung = [];
            foreach ($ds as $h) {
                $vung[$h['trang']] = [
                    min($vung[$h['trang']][0] ?? INF, $h['trang'] === $dau['trang'] ? $tuTren : $h['top'] - $khoang),
                    max($vung[$h['trang']][1] ?? -INF, $h['top'] + $khoang),
                ];
            }
            // Thân bảng dừng trước tiêu đề phần kế tiếp nằm ngay dưới bảng ("GIÁ TOUR
            // BAO GỒM :", "1. Vé máy bay…") — không thì dòng đó bị hút vào ô của
            // hàng giá cuối cùng và mất tiêu đề phần
            foreach ($vung as $trang => [$tren, $duoi]) {
                $hangCuoi = max(array_map(fn ($h) => $h['trang'] === $trang ? $h['top'] : -INF, $ds));
                foreach ($phanTu as $p) {
                    if ($p['loai'] === 'chu' && $p['trang'] === $trang && $p['top'] > $hangCuoi + 2 && $p['top'] <= $duoi
                        && ! self::tien($p['chu']) && self::laTieuDePhan($p['chu'])) {
                        $vung[$trang][1] = min($vung[$trang][1], $p['top'] - 1);
                    }
                }
            }
            $than = [];
            foreach ($phanTu as $i => $p) {
                if ($p['loai'] === 'chu' && ! isset($tieuDe[$i]) && isset($vung[$p['trang']])
                    && $p['top'] >= $vung[$p['trang']][0] && $p['top'] <= $vung[$p['trang']][1]) {
                    $than[$i] = $p;
                }
            }

            // Bảng có cột "THÁNG" riêng (HIGO): ô ngày chỉ ghi số ngày ("25, 30", "21"),
            // tháng nằm ở cột bên cạnh ("10") → ghép thành "Tháng 10: 25, 30"
            $giuaThang = null;
            foreach ($tieuDe as $p) {
                if (preg_match('/^THANG$/', trim(strtoupper(Str::ascii($p['chu']))))) {
                    $giuaThang = $p['left'] + $p['rong'] / 2;
                }
            }
            $thangHang = [];

            // Gắn mẩu chữ (không phải tiền) vào hàng
            $nhan = array_filter($than, fn ($p) => ! self::tien($p['chu']) && preg_match('/^\s*tháng\s*\d/iu', $p['chu']));
            $oNgay = array_fill(0, count($ds), []);
            $oKhac = array_fill(0, count($ds), []);
            $nhom = -1;
            foreach ($than as $i => $p) {
                if (self::tien($p['chu']) && $p['left'] >= $x0 - 3) {
                    continue; // ô tiền — lấy riêng bên dưới
                }
                $laNgay = $p['left'] < $x0 - 3 && preg_match('/\d{1,2}\s*\/\s*\d{1,2}|tháng\s*\d|^\s*(?:lễ\s*:?\s*)?\d{1,2}(?:\s*,\s*\d{1,2})+\s*,?\s*$/iu', $p['chu']);
                $giua = $p['left'] + $p['rong'] / 2;
                $soTron = $giuaThang !== null && $p['left'] < $x0 - 3 && preg_match('/^\s*\d{1,2}(?:\s*[,;&]\s*\d{1,2})*\s*,?\s*$/u', $p['chu']);
                $laThang = $soTron && abs($giua - $giuaThang) <= 30 && (int) $p['chu'] >= 1 && (int) $p['chu'] <= 12;
                $laNgay = $laNgay || ($soTron && ! $laThang && $giua < $giuaThang - 30);
                if ($laNgay && $nhan && count($nhan) === count($ds)) {
                    if (isset($nhan[$i])) {
                        $nhom++;
                    }
                    $oNgay[max(0, $nhom)][] = $p['chu'];

                    continue;
                }
                // Hàng gần nhất (cùng trang)
                $gan = null;
                foreach ($ds as $k => $h) {
                    if ($h['trang'] === $p['trang'] && ($gan === null || abs($h['top'] - $p['top']) < abs($ds[$gan]['top'] - $p['top']))) {
                        $gan = $k;
                    }
                }
                $gan ??= 0;
                if ($laThang) {
                    $thangHang[$gan] = (int) $p['chu'];

                    continue;
                }
                if ($laNgay) {
                    $oNgay[$gan][] = $p['chu'];
                } else {
                    $oKhac[$gan][] = $p['chu'];
                }
            }

            if ($thangHang) {
                // Ô tháng gộp nhiều hàng chỉ ghi chữ ở hàng giữa → hàng không có tháng
                // lấy tháng của hàng trên (hàng đầu thì lấy của hàng dưới gần nhất)
                $thang = $thangHang[min(array_keys($thangHang))];
                foreach ($ds as $k => $h) {
                    $thang = $thangHang[$k] ?? $thang;
                    if ($oNgay[$k] && ! preg_grep('/\/|tháng/iu', $oNgay[$k])) {
                        $oNgay[$k] = ["Tháng {$thang}: ".implode(', ', array_map('trim', $oNgay[$k]))];
                    }
                }
            }

            foreach ($ds as $k => $h) {
                $tienO = [];
                foreach ($h['tien'] as $i) {
                    $chu = $phanTu[$i]['chu'];
                    // Mẩu có cả chữ lẫn tiền ("26/04 (Lễ…) 63.900.000"): phần chữ đưa về ô ngày / ô khác
                    $con = trim(preg_replace(self::TIEN, '', $chu));
                    if ($con !== '' && ! preg_match('/^(vn[dđ]|đ|vnd|\s|\/|\.)*$/iu', $con)) {
                        preg_match('/\d{1,2}\s*\/\s*\d{1,2}|tháng\s*\d/iu', $con) ? $oNgay[$k][] = $con : $oKhac[$k][] = $con;
                    }
                    array_push($tienO, ...self::tien($chu));
                }
                $them[$h['tien'][0]] = [
                    'loai' => 'hang', 'trang' => $h['trang'], 'top' => $h['top'], 'cao' => $h['cao'], 'left' => 0,
                    'o' => [implode(' ', $oNgay[$k]), implode(' ', $oKhac[$k]), ...$tienO],
                ];
                foreach ($h['tien'] as $i) {
                    $daDung[$i] = true;
                }
            }
            foreach (array_keys($than) as $i) {
                $daDung[$i] = true;
            }
            if ($tieuDe) {
                // Gom mẩu cùng dòng, sát nhau thành cụm trước ("TRẺ" + "EM" — phông khác nhau nên
                // bị tách, xếp theo cột thì "EM" chạy ra sau "(từ 2 đến dưới 11 tuổi)" và mất
                // chữ "TRẺ EM"), rồi mới xếp các cụm theo cột (trái → phải, trên → dưới)
                $tieuDe = collect($tieuDe)->sortBy([['top', 'asc'], ['left', 'asc']])->all();
                $cum = [];
                foreach ($tieuDe as $p) {
                    foreach ($cum as $c => $x) {
                        $ho = $p['left'] - $x['phai'];
                        if (abs($x['top'] - $p['top']) <= 3 && $ho >= -2 && $ho <= 8) {
                            $cum[$c]['chu'] .= ' '.$p['chu'];
                            $cum[$c]['phai'] = $p['left'] + $p['rong'];

                            continue 2;
                        }
                    }
                    $cum[] = ['left' => $p['left'], 'top' => $p['top'], 'phai' => $p['left'] + $p['rong'], 'chu' => $p['chu']];
                }
                usort($cum, fn ($a, $b) => [$a['left'], $a['top']] <=> [$b['left'], $b['top']]);
                $i0 = array_key_first($tieuDe);
                $them[$i0] = ['loai' => 'hang', 'trang' => $dau['trang'], 'top' => $tuTren, 'cao' => 10, 'left' => 0,
                    'o' => [implode(' ', array_column($cum, 'chu')), '']];
                foreach (array_keys($tieuDe) as $i) {
                    $daDung[$i] = true;
                }
            }
        }

        $ra = [];
        foreach ($phanTu as $i => $p) {
            if (isset($them[$i])) {
                $ra[] = $them[$i];
            }
            if (! isset($daDung[$i])) {
                $ra[] = $p;
            }
        }
        usort($ra, fn ($a, $b) => [$a['trang'], $a['top'], $a['left']] <=> [$b['trang'], $b['top'], $b['left']]);

        return $ra;
    }
}
