<?php

namespace App\Services\Voucher;

/**
 * Mã vạch Code 128 (bộ B: chữ, số, dấu "-") dạng SVG — cho máy quét cầm tay
 * ở quầy. Mỗi mẫu là độ rộng 6 vạch / khoảng trắng (vạch trước), mã dừng 7.
 */
class MaVach128
{
    private const MAU = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    private const BAT_DAU_B = 104;

    private const DUNG = 106;

    /** @return list<int> độ rộng lần lượt vạch, trắng, vạch… (đơn vị mô-đun, chưa gồm vùng trống hai đầu) */
    public static function doRong(string $chu): array
    {
        $ma = [self::BAT_DAU_B];
        $tong = self::BAT_DAU_B;
        foreach (str_split($chu) as $i => $k) {
            $gt = ord($k) - 32;
            if ($gt < 0 || $gt > 94) {
                continue; // ngoài bộ B — bỏ (mã voucher chỉ có chữ hoa, số, "-")
            }
            $ma[] = $gt;
            $tong += $gt * ($i + 1);
        }
        $ma[] = $tong % 103;
        $ma[] = self::DUNG;

        $rong = [];
        foreach ($ma as $m) {
            foreach (str_split(self::MAU[$m]) as $w) {
                $rong[] = (int) $w;
            }
        }

        return $rong;
    }

    /**
     * SVG đen trắng, viewBox theo mô-đun — co giãn đúng tỉ lệ khi đặt vào ô bất kỳ.
     * $le = vùng trống hai đầu (mô-đun); 0 khi nền trắng xung quanh đã đủ rộng.
     */
    public static function svg(string $chu, int $cao = 40, int $le = 10): string
    {
        $x = $le;
        $vach = '';
        foreach (self::doRong($chu) as $i => $w) {
            if ($i % 2 === 0) {
                $vach .= '<rect x="'.$x.'" y="0" width="'.$w.'" height="'.$cao.'"/>';
            }
            $x += $w;
        }
        $tong = $x + $le;

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$tong.' '.$cao.'" preserveAspectRatio="none">'
            .'<rect width="'.$tong.'" height="'.$cao.'" fill="#fff"/><g fill="#000">'.$vach.'</g></svg>';
    }
}
