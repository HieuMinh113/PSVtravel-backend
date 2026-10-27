<?php

namespace Modules\Maldives\Services\NhapCombo;

use Illuminate\Support\Str;

/**
 * Đọc theo toạ độ những phần của file PDF combo Maldives mà đọc từng dòng chữ
 * sẽ lẫn lộn:
 *  - bảng giá USD (hạng phòng × ngày khởi hành): chữ trong ô căn giữa theo
 *    chiều dọc nên tên phòng, sức chứa, giá nằm lệch dòng nhau; có ô nằm lẻ
 *    một dòng riêng (Prestige "2.796"); một ô tiêu đề có khi trùm hai cột
 *    ("24/ 03/ 2027  29/ 04/ 2027"); bảng có thể vắt sang trang sau;
 *  - khung tiện ích chia nhiều cột ("MIỄN PHÍ TOUR | GIẢI TRÍ | SỨC KHỎE"):
 *    đọc theo dòng thì chữ của ba cột trộn vào nhau.
 * Mỗi phần trả kèm vùng [trang, trên, dưới] để bỏ các dòng tương ứng trong dãy
 * mục của DocPdf và thay bằng bảng / nhóm tiện ích đã dựng lại.
 */
class BoCucPdf
{
    /** Một ô giá: "2.631", "$2,631", "NA" (không bán) */
    private const O_GIA = '/^\$?\d{1,2}[.,]\d{3}$|^N\/?A$/i';

    /** Dòng ngay dưới bảng giá (so chữ không dấu, viết hoa) — hết bảng */
    private const HET_BANG = '/^(TY GIA|NOTE\b|LUU Y|GHI CHU|CHI PHI DANH CHO TRE|GIA TINH BANG|GIA DANH CHO TRE|DICH VU|RESORT (DANH|YEU)|TRE EM\b|UU DAI|GIA (DA )?BAO GOM|CHINH SACH|QUY DINH)/';

    /**
     * @param  list<array>  $manh  mẩu chữ có toạ độ (DocPdf::manhChu())
     * @return array{bang: ?array, khoi: list<array>}
     */
    public static function doc(array $manh): array
    {
        $manh = array_values(array_map(fn ($p) => $p + ['khoa' => $p['trang'] * 100000 + $p['top']], $manh));
        $dong = self::dong($manh);
        foreach ($dong as $k => $d) {
            foreach ($d['manh'] as $i) {
                $manh[$i]['dong'] = $k;
            }
        }
        $bang = self::bangGia($manh, $dong);

        return ['bang' => $bang, 'khoi' => self::khoiNhieuCot($manh, $dong, $bang['vung'] ?? [])];
    }

    // ===================================================================
    // Bảng giá
    // ===================================================================

    private static function bangGia(array $manh, array $dong): ?array
    {
        $dau = null;
        foreach ($dong as $k => $d) {
            if (preg_match('/GIA COMBO|BANG GIA/', self::khoa($d['chu']))) {
                $dau = $k;
                break;
            }
        }
        if ($dau === null) {
            return null;
        }
        $trang = $dong[$dau]['trang'];
        $tu = $dong[$dau]['khoa'];
        // Hết bảng: dòng đầu tiên khớp HET_BANG, trên trang đó hoặc trang sau; không thấy thì hết trang.
        // Vị trí so bằng "khoá" = trang × 100000 + toạ độ dọc (bảng vắt trang vẫn so được)
        $den = ($trang + 1) * 100000;
        $boDong = [$dau => true];
        foreach ($dong as $k => $d) {
            if ($d['khoa'] <= $tu || $d['trang'] > $trang + 1) {
                continue;
            }
            $khoa = self::khoa($d['chu']);
            if (preg_match(self::HET_BANG, $khoa)) {
                $den = $d['khoa'];
                break;
            }
            if (str_contains($khoa, 'PHU THU') || str_contains($khoa, 'GIA COMBO')) {
                $boDong[$k] = true; // "Khách khởi hành từ TP HCM phụ thu $100 - $120" — phần phân tích tự đọc
            }
        }

        // Ô giá và chữ trong vùng bảng
        $o = [];
        $chu = [];
        foreach ($manh as $i => $p) {
            if ($p['khoa'] <= $tu || $p['khoa'] >= $den - 2 || isset($boDong[$p['dong']])) {
                continue;
            }
            $tach = preg_split('/\s+/', trim($p['chu']));
            if (! array_filter($tach, fn ($x) => ! preg_match(self::O_GIA, $x))) {
                // một mẩu có thể gồm vài ô liền nhau ("2.631 2.817") → ước vị trí từng ô theo số ký tự
                $rongKyTu = $p['rong'] / max(1, mb_strlen(trim($p['chu'])));
                $viTri = 0;
                foreach ($tach as $x) {
                    $viTri = mb_strpos(trim($p['chu']), $x, $viTri);
                    $o[] = ['x' => $p['left'] + ($viTri + mb_strlen($x) / 2) * $rongKyTu, 'trai' => $p['left'] + $viTri * $rongKyTu,
                        'khoa' => $p['khoa'], 'trang' => $p['trang'], 'gia' => preg_match('/\d/', $x) ? (int) preg_replace('/\D/', '', $x) : null];
                    $viTri += mb_strlen($x);
                }

                continue;
            }
            $chu[$i] = $p;
        }
        if (! $o) {
            return null;
        }

        // Cột: gom các ô theo vị trí ngang
        usort($o, fn ($a, $b) => $a['x'] <=> $b['x']);
        $cot = [];
        foreach ($o as $k => $x) {
            if (! $cot || $x['x'] - $cot[count($cot) - 1]['x_cuoi'] > 30) {
                $cot[] = ['x' => [], 'x_cuoi' => $x['x'], 'trai' => $x['trai']];
            }
            $c = count($cot) - 1;
            $cot[$c]['x'][] = $x['x'];
            $cot[$c]['x_cuoi'] = $x['x'];
            $cot[$c]['trai'] = min($cot[$c]['trai'], $x['trai']);
            $o[$k]['cot'] = $c;
        }
        $giua = array_map(fn ($c) => array_sum($c['x']) / count($c['x']), $cot);
        $traiCot0 = $cot[0]['trai'];

        // Hàng: các ô cùng độ cao; hàng "neo" là hàng có đủ (≥ 60%) số ô — ô lẻ gắn vào hàng neo gần nhất còn trống cột đó
        usort($o, fn ($a, $b) => [$a['khoa'], $a['x']] <=> [$b['khoa'], $b['x']]);
        $hang = [];
        foreach ($o as $x) {
            $h = count($hang) - 1;
            if ($hang && abs($hang[$h]['khoa'] - $x['khoa']) <= 3) {
                $hang[$h]['o'][] = $x;
            } else {
                $hang[] = ['khoa' => $x['khoa'], 'trang' => $x['trang'], 'o' => [$x]];
            }
        }
        $nhieuNhat = max(array_map(fn ($h) => count($h['o']), $hang));
        $neo = [];
        $le = [];
        foreach ($hang as $h) {
            if (count($h['o']) >= max(1, (int) ceil($nhieuNhat * 0.6))) {
                $gia = [];
                foreach ($h['o'] as $x) {
                    $gia[$x['cot']] = $x['gia'];
                }
                $neo[] = ['khoa' => $h['khoa'], 'trang' => $h['trang'], 'gia' => $gia, 'dong' => []];
            } else {
                array_push($le, ...$h['o']);
            }
        }
        foreach ($le as $x) {
            $gan = null;
            foreach ($neo as $k => $n) {
                if (! array_key_exists($x['cot'], $n['gia']) && ($gan === null || abs($n['khoa'] - $x['khoa']) < abs($neo[$gan]['khoa'] - $x['khoa']))) {
                    $gan = $k;
                }
            }
            if ($gan !== null) {
                $neo[$gan]['gia'][$x['cot']] = $x['gia'];
            }
        }

        // Tiêu đề cột (ngày khởi hành) ở trên hàng neo đầu; tên hạng phòng + sức chứa ở bên trái cột giá đầu
        $tieuDe = [];
        $ben = [];
        foreach ($chu as $i => $p) {
            if ($traiCot0 - 10 > $p['left'] + $p['rong'] / 2) {
                $ben[$i] = $p;
            } elseif ($p['khoa'] < $neo[0]['khoa'] - 3) {
                $tieuDe[$i] = $p;
            }
        }
        $duoiTieuDe = $tieuDe ? max(array_map(fn ($p) => $p['khoa'] + $p['cao'], $tieuDe)) : $tu;
        $cotChu = array_fill(0, count($cot), []);
        foreach ($tieuDe as $p) {
            // mẩu trùm nhiều cột: tách theo khoảng trắng đôi
            $trum = array_keys(array_filter($giua, fn ($g) => $g >= $p['left'] - 5 && $g <= $p['left'] + $p['rong'] + 5));
            $manhNho = preg_split('/\s{2,}/u', trim($p['chu']));
            if (count($trum) >= 2 && count($manhNho) === count($trum)) {
                foreach ($trum as $k => $c) {
                    $cotChu[$c][] = ['khoa' => $p['khoa'], 'left' => $p['left'], 'chu' => $manhNho[$k]];
                }

                continue;
            }
            $x = $p['left'] + $p['rong'] / 2;
            $gan = 0;
            foreach ($giua as $c => $g) {
                if (abs($g - $x) < abs($giua[$gan] - $x)) {
                    $gan = $c;
                }
            }
            $cotChu[$gan][] = ['khoa' => $p['khoa'], 'left' => $p['left'], 'chu' => trim($p['chu'])];
        }
        $cotChu = array_map(function ($ds) {
            usort($ds, fn ($a, $b) => [$a['khoa'], $a['left']] <=> [$b['khoa'], $b['left']]);

            return trim(preg_replace('/\s+/u', ' ', implode(' ', array_column($ds, 'chu'))));
        }, $cotChu);

        // Dòng chữ bên trái → hàng neo: dòng thuộc hàng neo đầu tiên mà nó không nằm dưới quá "dung sai"
        // (dung sai ≈ nửa khoảng cách hai hàng — tên phòng dòng trên, sức chứa dòng dưới ô giá)
        $dongBen = self::dong(array_values(array_filter($ben, fn ($p) => $p['khoa'] >= $duoiTieuDe - 2 && ! preg_match('/^\d{1,2}$/', trim($p['chu'])))));
        $cach = [];
        foreach ($neo as $k => $n) {
            if ($k > 0 && $neo[$k - 1]['trang'] === $n['trang']) {
                $cach[] = $n['khoa'] - $neo[$k - 1]['khoa'];
            }
        }
        sort($cach);
        $dungSai = $cach ? min(14, 0.45 * $cach[intdiv(count($cach), 2)]) : 14;
        foreach ($dongBen as $d) {
            $vao = count($neo) - 1;
            foreach ($neo as $k => $n) {
                if ($d['khoa'] <= $n['khoa'] + $dungSai) {
                    $vao = $k;
                    break;
                }
            }
            $neo[$vao]['dong'][] = $d['chu'];
        }

        // Vùng bỏ khỏi dãy mục: từ chữ / ô đầu tiên của bảng tới hết bảng
        $tren = min(array_merge(array_column($o, 'khoa'), array_map(fn ($p) => $p['khoa'], $tieuDe ?: $ben ?: [['khoa' => $neo[0]['khoa']]])));
        $vung = [];
        for ($t = intdiv((int) $tren, 100000); $t <= intdiv((int) ($den - 1), 100000) && $t <= $trang + 1; $t++) {
            $vung[] = [$t, $t * 100000 <= $tren ? $tren - $t * 100000 - 1 : -1, $den < ($t + 1) * 100000 ? $den - $t * 100000 - 1 : 99999];
        }

        return [
            'cot' => $cotChu,
            'hang' => array_map(fn ($n) => ['dong' => $n['dong'], 'gia' => $n['gia']], $neo),
            'vung' => $vung,
        ];
    }

    // ===================================================================
    // Khung tiện ích nhiều cột
    // ===================================================================

    /**
     * Hàng tiêu đề: ≥ 2 cụm chữ đậm, viết hoa, ngắn, cùng độ cao (lệch ≤ 10
     * điểm) và không chồng nhau theo chiều ngang. Thân khung: các dòng bên dưới
     * tới hàng tiêu đề kế tiếp / tiêu đề ngày / đoạn chữ chạy ngang cả khung /
     * khoảng trống lớn / hết trang. Mỗi mẩu chữ vào cột theo vị trí giữa mẩu.
     *
     * @param  list<array>  $vungBo  vùng bảng giá (bỏ qua)
     * @return list<array{vung: array, nhom: list<array{title: string, items: list<string>}>}>
     */
    private static function khoiNhieuCot(array $manh, array $dong, array $vungBo): array
    {
        $trongBang = fn (int $trang, float $top) => (bool) array_filter($vungBo, fn ($v) => $v[0] === $trang && $top >= $v[1] && $top <= $v[2]);

        // Cụm tiêu đề ứng viên
        $ung = [];
        foreach ($dong as $k => $d) {
            if (str_contains(self::khoa($d['chu']), 'LOAI PHONG')) {
                continue; // bảng thông tin phòng (loại phòng | số khách | giường) — không phải tiện ích
            }
            foreach ($d['cum'] as $c) {
                $khoa = self::khoa($c['chu']);
                if ($c['dam'] && self::hoa($c['chu']) && mb_strlen($c['chu']) <= 60 && ! preg_match('/^NGAY\s*\d/', $khoa)
                    && ! $trongBang($d['trang'], $d['top'])) {
                    $ung[] = $c + ['trang' => $d['trang'], 'top' => $d['top'], 'cao' => $d['cao'], 'khoa' => $d['khoa'], 'dong' => $k];
                }
            }
        }
        $hangTieuDe = [];
        foreach ($ung as $c) {
            foreach ($hangTieuDe as $h => $ds) {
                if ($ds[0]['trang'] === $c['trang'] && abs($ds[0]['top'] - $c['top']) <= 10
                    && ! array_filter($ds, fn ($x) => min($x['phai'], $c['phai']) > max($x['trai'], $c['trai']))) {
                    $hangTieuDe[$h][] = $c;

                    continue 2;
                }
            }
            $hangTieuDe[] = [$c];
        }
        $hangTieuDe = array_values(array_filter($hangTieuDe, fn ($ds) => count($ds) >= 2));

        $khoi = [];
        foreach ($hangTieuDe as $h => $ds) {
            usort($ds, fn ($a, $b) => $a['trai'] <=> $b['trai']);
            $giua = array_map(fn ($c) => ($c['trai'] + $c['phai']) / 2, $ds);
            $bien = [];
            for ($i = 1; $i < count($giua); $i++) {
                $bien[] = ($giua[$i - 1] + $giua[$i]) / 2;
            }
            $trang = $ds[0]['trang'];
            $tuTren = min(array_column($ds, 'top'));
            $duoiTieuDe = max(array_map(fn ($c) => $c['top'] + $c['cao'], $ds));
            $dongTieuDe = array_column($ds, 'dong');
            $hangSau = $hangTieuDe[$h + 1] ?? null;

            // Thân khung
            $than = [];
            $truoc = $duoiTieuDe;
            foreach ($dong as $k => $d) {
                if ($d['trang'] !== $trang || $d['top'] <= $tuTren || in_array($k, $dongTieuDe, true)) {
                    continue;
                }
                if (preg_match('/^\d{1,2}$/', $d['chu'])) {
                    continue; // số trang
                }
                // một mẩu chữ (không phải cụm mẩu sát nhau ở hai cột) chạy ngang qua mọi cột = đoạn văn thường, hết khung
                $chayNgang = (bool) array_filter($d['manh'], fn ($i) => $manh[$i]['left'] < $bien[0] - 15 && $manh[$i]['left'] + $manh[$i]['rong'] > $bien[count($bien) - 1] + 15);
                if (($hangSau && $hangSau[0]['trang'] === $trang && $d['top'] >= min(array_column($hangSau, 'top')) - 3)
                    || preg_match('/^NGAY\s*\d/', self::khoa($d['chu'])) || $chayNgang || $d['top'] - $truoc > 40) {
                    break;
                }
                $than[] = $d;
                $truoc = $d['top'] + $d['cao'];
            }
            if (! $than) {
                continue;
            }

            // Mẩu chữ → cột; gạch đầu dòng đứng riêng gắn vào mẩu ngay bên phải
            $manhThan = [];
            foreach ($than as $d) {
                foreach ($d['manh'] as $i) {
                    $manhThan[$i] = $manh[$i];
                }
            }
            $gach = [];
            foreach ($manhThan as $i => $p) {
                if (preg_match('/^[-–•●▪+✓✔]$/u', trim($p['chu']))) {
                    $phai = null;
                    foreach ($manhThan as $j => $q) {
                        $ho = $q['left'] - ($p['left'] + $p['rong']);
                        if ($j !== $i && abs($q['top'] - $p['top']) <= 5 && $ho >= -1 && $ho <= 20 && ($phai === null || $q['left'] < $manhThan[$phai]['left'])) {
                            $phai = $j;
                        }
                    }
                    if ($phai !== null) {
                        $gach[$phai] = true;
                    }
                    unset($manhThan[$i]);
                }
            }
            $theoCot = array_fill(0, count($ds), []);
            foreach ($manhThan as $i => $p) {
                $x = $p['left'] + $p['rong'] / 2;
                $c = count(array_filter($bien, fn ($b) => $b < $x));
                $theoCot[$c][] = $p + ['gach' => isset($gach[$i])];
            }

            $nhom = [];
            foreach ($ds as $c => $tieuDe) {
                $muc = [];
                foreach (self::dong($theoCot[$c]) as $d) {
                    $coGach = (bool) array_filter($d['manh'], fn ($i) => $theoCot[$c][$i]['gach']);
                    $chu = $d['chu'];
                    if (preg_match('/^[-–•●▪+✓✔]\s*/u', $chu)) {
                        $chu = trim(preg_replace('/^[-–•●▪+✓✔]\s*/u', '', $chu));
                        $coGach = true;
                    }
                    if ($chu === '') {
                        continue;
                    }
                    $cuoi = $muc ? $muc[count($muc) - 1] : null;
                    if ($cuoi !== null && ! $coGach && (preg_match('/^\p{Ll}/u', $chu) || preg_match('/[,–\-]$/u', $cuoi))) {
                        $muc[count($muc) - 1] = $cuoi.' '.$chu;
                    } else {
                        $muc[] = $chu;
                    }
                }
                $ten = $tieuDe['chu'];
                // dòng phụ đề trong ngoặc ngay dưới tiêu đề: "(giá tham khảo, tự túc chi phí)"
                if ($muc && preg_match('/^\(.*\)$/u', $muc[0])) {
                    $ten .= ' '.array_shift($muc);
                }
                $nhom[] = ['title' => $ten, 'items' => $muc];
            }

            $khoi[] = [
                'vung' => [$trang, min(array_column($ds, 'top')) - 1, $truoc + 1],
                'nhom' => $nhom,
            ];
        }

        return $khoi;
    }

    // ===================================================================
    // Tiện ích
    // ===================================================================

    /**
     * Gom mẩu chữ thành dòng (cùng trang, lệch ≤ 3 điểm), nối chữ theo dấu
     * cách ở mép mẩu; mỗi dòng kèm các cụm (mẩu cách nhau > 25 điểm = cụm khác).
     *
     * @param  list<array>  $manh
     * @return list<array{trang: int, top: float, cao: float, khoa: float, chu: string, manh: list<int>, cum: list<array>}>
     */
    private static function dong(array $manh): array
    {
        $thuTu = array_keys($manh);
        usort($thuTu, fn ($a, $b) => [$manh[$a]['trang'], $manh[$a]['top'], $manh[$a]['left']] <=> [$manh[$b]['trang'], $manh[$b]['top'], $manh[$b]['left']]);
        $dong = [];
        foreach ($thuTu as $i) {
            $p = $manh[$i];
            $k = count($dong) - 1;
            if ($dong && $dong[$k]['trang'] === $p['trang'] && abs($dong[$k]['top'] - $p['top']) <= 3) {
                $dong[$k]['manh'][] = $i;
                $dong[$k]['cao'] = max($dong[$k]['cao'], $p['cao']);
            } else {
                $dong[] = ['trang' => $p['trang'], 'top' => $p['top'], 'cao' => $p['cao'], 'khoa' => $p['khoa'] ?? $p['trang'] * 100000 + $p['top'], 'manh' => [$i]];
            }
        }

        foreach ($dong as $k => $d) {
            usort($d['manh'], fn ($a, $b) => $manh[$a]['left'] <=> $manh[$b]['left']);
            $chu = '';
            $cum = [];
            $truoc = null;
            foreach ($d['manh'] as $i) {
                $p = $manh[$i];
                $ho = $truoc ? $p['left'] - ($truoc['left'] + $truoc['rong']) : null;
                if ($truoc === null || $ho > 25) {
                    $cum[] = ['chu' => '', 'trai' => $p['left'], 'phai' => $p['left'] + $p['rong'], 'dam' => true];
                }
                $c = count($cum) - 1;
                $cach = $truoc !== null && $ho <= 25 && (($truoc['cach_cuoi'] ?? false) || ($p['cach_dau'] ?? false) || $ho > 1.5) ? ' ' : '';
                $cum[$c]['chu'] .= $cach.trim($p['chu']);
                $cum[$c]['phai'] = max($cum[$c]['phai'], $p['left'] + $p['rong']);
                $cum[$c]['dam'] = $cum[$c]['dam'] && ($p['dam'] ?? false);
                $chu .= ($truoc === null ? '' : ($cach === '' && $ho <= 25 ? '' : ' ')).trim($p['chu']);
                $truoc = $p;
            }
            $dong[$k]['manh'] = $d['manh'];
            $dong[$k]['chu'] = trim(preg_replace('/\s+/u', ' ', $chu));
            $dong[$k]['cum'] = array_map(fn ($c) => ['chu' => trim(preg_replace('/\s+/u', ' ', $c['chu']))] + $c, $cum);
        }

        return $dong;
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
}
