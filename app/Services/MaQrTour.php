<?php

namespace App\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Modules\Tour\Models\Tour;
use Modules\Tour\Models\TourQrScan;

/**
 * Mã QR cho từng tour — dùng in tờ rơi, poster, đăng mạng xã hội.
 *
 * Mã KHÔNG chứa đường dẫn tour, mà chứa link ngắn cố định:
 *     https://psvtravel.com/qr/{id tour}
 * Website nhận link này, ghi một lượt quét rồi chuyển tiếp tới trang tour.
 * Nhờ vậy:
 *  - Đổi tên tour (đường dẫn đổi theo) thì tờ rơi đã in vẫn quét được.
 *  - Đếm được lượt quét của từng tour.
 *
 * Mức sửa lỗi H (khôi phục được ~30% mã bị che) nên đặt logo ở giữa vẫn quét
 * ổn định. Logo chỉ chiếm ~22% bề ngang (≈5% diện tích), dư sức an toàn.
 *
 * Vẽ thẳng từ ma trận điểm của thư viện bacon/bacon-qr-code (đã có sẵn), dùng
 * GD cho PNG — không cần imagick (VPS không có).
 */
class MaQrTour
{
    /** Xanh thương hiệu — lấy mẫu từ chữ "PSV" trên logo. */
    public const MAU = '#0036AD';

    /** Vùng trắng quanh mã (tính bằng ô), chuẩn QR yêu cầu tối thiểu 4. */
    private const VIEN = 4;

    /** Ô logo chiếm khoảng chừng này bề ngang mã. */
    private const TI_LE_LOGO = 0.22;

    public static function duongDan(Tour $tour): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/qr/'.$tour->getKey();
    }

    public static function tenTep(Tour $tour, string $duoi): string
    {
        return 'ma-qr-'.($tour->slug ?: 'tour-'.$tour->getKey()).'.'.$duoi;
    }

    /** Ma trận điểm: true = ô tối. */
    private function maTran(string $noiDung): array
    {
        $m = Encoder::encode($noiDung, ErrorCorrectionLevel::H(), 'UTF-8')->getMatrix();
        $n = $m->getWidth();
        $o = [];
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                $o[$y][$x] = $m->get($x, $y) === 1;
            }
        }

        return $o;
    }

    /**
     * Ô logo tính theo số ô của mã: số lẻ để nằm chính giữa, và cộng thêm một
     * vành trắng mỏng để logo không dính vào các ô xung quanh.
     *
     * @return array{0:int,1:int} [ô bắt đầu, độ rộng] trên lưới (chưa tính viền)
     */
    private function oLogo(int $n): array
    {
        $rong = (int) round($n * self::TI_LE_LOGO);
        if ($rong % 2 === 0) {
            $rong++;
        }

        return [intdiv($n - $rong, 2), $rong];
    }

    public function svg(Tour $tour): string
    {
        $m = $this->maTran(self::duongDan($tour));
        $n = count($m);
        [$batDau, $rong] = $this->oLogo($n);
        $tong = $n + 2 * self::VIEN;

        // Gộp các ô tối liền nhau trên cùng một hàng thành một đoạn — tệp nhỏ
        // hơn nhiều so với vẽ từng ô. Bỏ qua các ô nằm dưới logo.
        $duong = '';
        for ($y = 0; $y < $n; $y++) {
            $x = 0;
            while ($x < $n) {
                if (! $m[$y][$x] || $this->trongLogo($x, $y, $batDau, $rong)) {
                    $x++;
                    continue;
                }
                $dau = $x;
                while ($x < $n && $m[$y][$x] && ! $this->trongLogo($x, $y, $batDau, $rong)) {
                    $x++;
                }
                $duong .= 'M'.($dau + self::VIEN).' '.($y + self::VIEN).'h'.($x - $dau).'v1h-'.($x - $dau).'z';
            }
        }

        $logo = base64_encode((string) file_get_contents($this->tepLogo()));
        $o = $batDau + self::VIEN;
        $dem = $rong * 0.1; // lề trong của ô logo

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$tong.' '.$tong.'" width="1024" height="1024" shape-rendering="crispEdges">'
            .'<rect width="100%" height="100%" fill="#FFFFFF"/>'
            .'<path fill="'.self::MAU.'" d="'.$duong.'"/>'
            .'<rect x="'.$o.'" y="'.$o.'" width="'.$rong.'" height="'.$rong.'" rx="'.($rong * 0.18).'" fill="#FFFFFF" shape-rendering="geometricPrecision"/>'
            .'<image x="'.($o + $dem).'" y="'.($o + $dem).'" width="'.($rong - 2 * $dem).'" height="'.($rong - 2 * $dem).'" '
            .'href="data:image/png;base64,'.$logo.'" preserveAspectRatio="xMidYMid meet"/>'
            .'</svg>';
    }

    /** PNG vuông khoảng $canhPx điểm ảnh (làm tròn theo số ô để nét sắc). */
    public function png(Tour $tour, int $canhPx = 1200): string
    {
        $m = $this->maTran(self::duongDan($tour));
        $n = count($m);
        [$batDau, $rong] = $this->oLogo($n);
        $tong = $n + 2 * self::VIEN;
        $o = max(1, intdiv($canhPx, $tong)); // điểm ảnh mỗi ô
        $canh = $o * $tong;

        $anh = imagecreatetruecolor($canh, $canh);
        imagealphablending($anh, true);
        $trang = imagecolorallocate($anh, 255, 255, 255);
        [$r, $g, $b] = sscanf(self::MAU, '#%02x%02x%02x');
        $xanh = imagecolorallocate($anh, $r, $g, $b);
        imagefilledrectangle($anh, 0, 0, $canh - 1, $canh - 1, $trang);

        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($m[$y][$x] && ! $this->trongLogo($x, $y, $batDau, $rong)) {
                    $px = ($x + self::VIEN) * $o;
                    $py = ($y + self::VIEN) * $o;
                    imagefilledrectangle($anh, $px, $py, $px + $o - 1, $py + $o - 1, $xanh);
                }
            }
        }

        // Ô nền trắng bo góc cho logo (GD không có hình chữ nhật bo góc: ghép
        // hai hình chữ nhật chéo nhau + bốn hình tròn ở góc)
        $x0 = ($batDau + self::VIEN) * $o;
        $c = $rong * $o;
        $bk = (int) round($c * 0.18);
        imagefilledrectangle($anh, $x0 + $bk, $x0, $x0 + $c - 1 - $bk, $x0 + $c - 1, $trang);
        imagefilledrectangle($anh, $x0, $x0 + $bk, $x0 + $c - 1, $x0 + $c - 1 - $bk, $trang);
        foreach ([[$x0 + $bk, $x0 + $bk], [$x0 + $c - 1 - $bk, $x0 + $bk], [$x0 + $bk, $x0 + $c - 1 - $bk], [$x0 + $c - 1 - $bk, $x0 + $c - 1 - $bk]] as [$cx, $cy]) {
            imagefilledellipse($anh, $cx, $cy, 2 * $bk, 2 * $bk, $trang);
        }

        $logo = imagecreatefrompng($this->tepLogo());
        $dem = (int) round($c * 0.1);
        imagecopyresampled($anh, $logo, $x0 + $dem, $x0 + $dem, 0, 0, $c - 2 * $dem, $c - 2 * $dem, imagesx($logo), imagesy($logo));

        ob_start();
        imagepng($anh, null, 9);
        $du = (string) ob_get_clean();
        imagedestroy($logo);
        imagedestroy($anh);

        return $du;
    }

    private function trongLogo(int $x, int $y, int $batDau, int $rong): bool
    {
        return $x >= $batDau && $x < $batDau + $rong && $y >= $batDau && $y < $batDau + $rong;
    }

    private function tepLogo(): string
    {
        return resource_path('images/logo-qr.png');
    }

    /**
     * Thống kê lượt quét của một tour.
     *
     * @return array{tong:int,hom_nay:int,bay_ngay:int,ba_muoi_ngay:int,dien_thoai:int,gan_nhat:?\Illuminate\Support\Carbon}
     */
    public static function thongKe(Tour $tour): array
    {
        $q = fn () => TourQrScan::query()->where('tour_id', $tour->getKey());

        return [
            'tong' => $q()->count(),
            'hom_nay' => $q()->where('scanned_at', '>=', now()->startOfDay())->count(),
            'bay_ngay' => $q()->where('scanned_at', '>=', now()->subDays(7))->count(),
            'ba_muoi_ngay' => $q()->where('scanned_at', '>=', now()->subDays(30))->count(),
            'dien_thoai' => $q()->where('thiet_bi', 'dien_thoai')->count(),
            'gan_nhat' => $q()->max('scanned_at') ? \Illuminate\Support\Carbon::parse($q()->max('scanned_at')) : null,
        ];
    }
}
