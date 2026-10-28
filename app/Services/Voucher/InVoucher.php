<?php

namespace App\Services\Voucher;

use App\Models\Voucher;
use App\Models\VoucherBatch;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use GdImage;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Dựng file in voucher: ảnh 2 mặt của đợt (bạn thiết kế) + ô trắng chứa mã,
 * QR (link kiểm tra trên website) và tuỳ chọn mã vạch, đặt ở góc đã chọn.
 *
 *  - PDF nhà in: mỗi voucher 2 trang (trước, sau) — bản tràn lề 3 mm
 *    (161×106 mm) hoặc bản thành phẩm 155×100 mm
 *  - PDF máy in văn phòng: A4 dọc, 2 voucher / tờ, có vạch cắt; trang lẻ mặt
 *    trước, trang chẵn mặt sau (in 2 mặt, lật cạnh dài)
 *  - Ảnh JPG từng voucher để gửi khách qua Zalo / Facebook
 * Ảnh nền chỉ nhúng một lần vào PDF dù in 1000 voucher (file vài MB).
 */
class InVoucher
{
    public const KIEU = [
        'tran_le' => 'Gửi nhà in — có tràn lề 3 mm (161×106 mm)',
        'thanh_pham' => 'Gửi nhà in — đúng khổ thành phẩm (155×100 mm)',
        'a4' => 'Máy in văn phòng — A4, 2 voucher / tờ, có vạch cắt',
    ];

    /** Khổ thành phẩm và tràn lề (mm) */
    public const RONG = 155;

    public const CAO = 100;

    public const TRAN = 3;

    /** A4 dọc: 2 voucher căn giữa, cách nhau 16 mm (khổ 155 mm nên không xếp được 4 / tờ) */
    public const A4_TRAI = 27.5;

    public const A4_TREN = [40.5, 156.5];

    /** Trên số này thì tạo file chạy nền, xong báo chuông (tránh chờ quá lâu) */
    public const TOI_DA_TAO_NGAY = 300;

    /** Số ảnh tối đa mỗi file ZIP */
    public const TOI_DA_ANH = 200;

    private ?Writer $qr = null;

    public static function urlKiemTra(string $ma): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/voucher/'.$ma;
    }

    /**
     * @param  iterable<Voucher>  $vouchers
     * @return string nội dung PDF
     */
    public function pdf(VoucherBatch $d, iterable $vouchers, string $kieu): string
    {
        if (! $d->front_image || ! Storage::disk('public')->exists($d->front_image)) {
            throw new RuntimeException('Đợt này chưa có ảnh mặt trước — tải ảnh lên ở phần "Mẫu in" rồi xuất lại.');
        }
        @set_time_limit(300);
        $coSau = $d->back_image && Storage::disk('public')->exists($d->back_image);
        $tranLe = $kieu === 'tran_le';
        $mat = fn (string $tep) => ['duong' => Storage::disk('public')->path($tep), 'boc' => self::bocAnh(Storage::disk('public')->path($tep), $tranLe)];

        $matMa = $coSau ? $d->code_side : 'front';
        $ds = collect($vouchers)->map(fn (Voucher $v) => [
            'ma' => $v->code,
            'seri' => $v->serial,
            'qr' => 'data:image/svg+xml;base64,'.base64_encode($this->qrSvg(self::urlKiemTra($v->code))),
            'vach' => $d->show_barcode ? 'data:image/svg+xml;base64,'.base64_encode(MaVach128::svg(Voucher::khoa($v->code), 40, 0)) : null,
        ])->values()->all();

        // Mỗi trang: danh sách thẻ (x, y = góc trên trái mép cắt trên trang, mm)
        $the = fn (array $v, string $mat, float $x, float $y) => ['x' => $x, 'y' => $y, 'mat' => $mat, 'v' => ($mat === 'truoc') === ($matMa === 'front') ? $v : null];
        $trang = [];
        if ($kieu === 'a4') {
            $soTo = (int) ceil(count($ds) / 2);
            foreach (array_chunk($ds, 2) as $i => $cap) {
                $vt = fn (string $mat) => array_map(fn (array $v, int $k) => $the($v, $mat, self::A4_TRAI, self::A4_TREN[$k]), $cap, array_keys($cap));
                $trang[] = ['the' => $vt('truoc'), 'ghi' => 'Tờ '.($i + 1).'/'.$soTo.' — mặt trước'];
                if ($coSau) {
                    $trang[] = ['the' => $vt('sau'), 'ghi' => 'Tờ '.($i + 1).'/'.$soTo.' — mặt sau'];
                }
            }
        } else {
            $le = $tranLe ? self::TRAN : 0;
            foreach ($ds as $v) {
                $trang[] = ['the' => [$the($v, 'truoc', $le, $le)], 'ghi' => null];
                if ($coSau) {
                    $trang[] = ['the' => [$the($v, 'sau', $le, $le)], 'ghi' => null];
                }
            }
        }

        $kho = $kieu === 'a4' ? [210, 297] : [self::RONG + 2 * ($tranLe ? self::TRAN : 0), self::CAO + 2 * ($tranLe ? self::TRAN : 0)];
        $html = view('pdf.voucher', [
            'ten' => $d->name,
            'kieu' => $kieu,
            'mat' => ['truoc' => $mat($d->front_image), 'sau' => $coSau ? $mat($d->back_image) : null],
            'o' => self::oMa($d),
            'trang' => $trang,
            'tran' => $tranLe ? self::TRAN : 0,
            'kho' => $kho,
        ])->render();

        return Pdf::setOptions(['isRemoteEnabled' => false, 'chroot' => [Storage::disk('public')->path(''), resource_path()], 'dpi' => 96, 'defaultFont' => 'DejaVu Sans'])
            ->loadHTML($html)->setPaper([0, 0, $kho[0] * 72 / 25.4, $kho[1] * 72 / 25.4])->output();
    }

    /**
     * Ô mã (toạ độ mm tính từ góc trên trái mép cắt thành phẩm; các mốc bên
     * trong tính từ mép trên ô). Ô rộng 28 mm thì cao 30 mm. Có mã vạch thì ô rộng
     * tối thiểu 36 mm để vạch đủ to cho máy quét (mô-đun ~0,2 mm), mã vạch mang mã
     * không gạch ngang; lề trắng 2 mm của ô làm vùng trống hai đầu.
     *
     * @return array{trai: float, tren: float, rong: float, cao: float, qr: float, qrTrai: float, qrTren: float, maTren: float, vachTren: float, chanTren: float, vach: bool}
     */
    public static function oMa(VoucherBatch $d): array
    {
        $vach = (bool) $d->show_barcode;
        $rong = (float) max($vach ? 36 : 20, min(45, (float) ($d->code_size_mm ?: 28)));
        $le = (float) max(3, min(20, (float) ($d->code_margin_mm ?: 7)));
        $qr = $vach ? min($rong - 8, 24) : $rong - 8;
        $maTren = 2.2 + $qr + 0.6;
        $chanTren = $maTren + 3.4 + ($vach ? 7 : 0);
        $cao = $chanTren + 2.6 + 1.2;
        $phai = str_ends_with((string) $d->code_corner, 'right');
        $duoi = str_starts_with((string) $d->code_corner, 'bottom');

        return [
            'trai' => $phai ? self::RONG - $le - $rong : $le,
            'tren' => $duoi ? self::CAO - $le - $cao : $le,
            'rong' => $rong,
            'cao' => $cao,
            'qr' => $qr,
            'qrTrai' => ($rong - $qr) / 2,
            'qrTren' => 2.2,
            'maTren' => $maTren,
            'vachTren' => $maTren + 3.7,
            'chanTren' => $chanTren,
            'vach' => $vach,
        ];
    }

    /** Cỡ chữ mã (mm) — vừa ngang ô */
    public static function coChuMa(array $o, string $ma): float
    {
        return min(2.6, ($o['rong'] - 3) / max(1, strlen($ma) * 0.62));
    }

    /** Ảnh có tràn lề 3 mm (tỉ lệ 161:106) hay đúng khổ thành phẩm (155:100) */
    public static function anhCoTranLe(string $duong): bool
    {
        $kt = @getimagesize($duong);
        if (! $kt || ! $kt[1]) {
            return false;
        }
        $tiLe = $kt[0] / $kt[1];

        return abs($tiLe - (self::RONG + 2 * self::TRAN) / (self::CAO + 2 * self::TRAN)) < abs($tiLe - self::RONG / self::CAO);
    }

    /**
     * Đặt ảnh nền sao cho phần thành phẩm của ảnh khớp đúng mép cắt.
     * Ảnh có tràn lề: phủ cả vùng tràn. Ảnh đúng khổ thành phẩm mà cần bản tràn
     * lề: phóng nhẹ (~6%) cho phủ kín vùng tràn.
     *
     * @return array{trai: float, tren: float, rong: float, cao: float} mm, gốc = góc trên trái mép cắt
     */
    public static function bocAnh(string $duong, bool $canTranLe): array
    {
        if (self::anhCoTranLe($duong)) {
            return ['trai' => -self::TRAN, 'tren' => -self::TRAN, 'rong' => self::RONG + 2 * self::TRAN, 'cao' => self::CAO + 2 * self::TRAN];
        }
        if (! $canTranLe) {
            return ['trai' => 0, 'tren' => 0, 'rong' => self::RONG, 'cao' => self::CAO];
        }
        $tiLe = max((self::RONG + 2 * self::TRAN) / self::RONG, (self::CAO + 2 * self::TRAN) / self::CAO);
        $r = self::RONG * $tiLe;
        $c = self::CAO * $tiLe;

        return ['trai' => (self::RONG - $r) / 2, 'tren' => (self::CAO - $c) / 2, 'rong' => $r, 'cao' => $c];
    }

    // ===================================================================
    // Ảnh JPG từng voucher
    // ===================================================================

    /**
     * ZIP ảnh JPG: mỗi voucher một ảnh mặt có mã (tên file = mã), cộng ảnh mặt
     * còn lại một lần.
     *
     * @param  iterable<Voucher>  $vouchers
     * @return string đường dẫn file ZIP tạm
     */
    public function zipAnh(VoucherBatch $d, iterable $vouchers): string
    {
        if (! $d->front_image || ! Storage::disk('public')->exists($d->front_image)) {
            throw new RuntimeException('Đợt này chưa có ảnh mặt trước — tải ảnh lên ở phần "Mẫu in" rồi xuất lại.');
        }
        @set_time_limit(300);
        $coSau = $d->back_image && Storage::disk('public')->exists($d->back_image);
        $matMa = $coSau ? $d->code_side : 'front';
        $nenMa = $this->nen(Storage::disk('public')->path($matMa === 'back' ? $d->back_image : $d->front_image));

        $tep = tempnam(sys_get_temp_dir(), 'voucher').'.zip';
        $zip = new ZipArchive;
        if ($zip->open($tep, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Không tạo được file ZIP.');
        }
        foreach ($vouchers as $v) {
            $zip->addFromString($v->code.'.jpg', $this->jpg($nenMa, $d, $v));
        }
        $con = $matMa === 'back' ? $d->front_image : ($coSau ? $d->back_image : null);
        if ($con) {
            $nen = $this->nen(Storage::disk('public')->path($con));
            ob_start();
            imagejpeg($nen, null, 90);
            $zip->addFromString(($matMa === 'back' ? 'mat-truoc' : 'mat-sau').'.jpg', (string) ob_get_clean());
        }
        $zip->close();

        return $tep;
    }

    /** Ảnh một voucher (mặt có mã) — xem thử / gửi khách */
    public function jpgMot(VoucherBatch $d, Voucher $v, int $rong = 2000): string
    {
        $coSau = $d->back_image && Storage::disk('public')->exists($d->back_image);
        $nen = $this->nen(Storage::disk('public')->path($coSau && $d->code_side === 'back' ? $d->back_image : $d->front_image), $rong);

        return $this->jpg($nen, $d, $v);
    }

    /** Ảnh nền cắt đúng khổ thành phẩm; mặc định ngang 2000 px (~330 dpi — đủ nét cho mã vạch) */
    private function nen(string $duong, int $rong = 2000): GdImage
    {
        $goc = @imagecreatefromstring((string) file_get_contents($duong));
        if (! $goc instanceof GdImage) {
            throw new RuntimeException('Không đọc được ảnh mẫu voucher (chỉ nhận JPG / PNG / WebP).');
        }
        $w = imagesx($goc);
        $h = imagesy($goc);
        [$x, $y, $cw, $ch] = self::anhCoTranLe($duong)
            ? [$w * self::TRAN / (self::RONG + 2 * self::TRAN), $h * self::TRAN / (self::CAO + 2 * self::TRAN), $w * self::RONG / (self::RONG + 2 * self::TRAN), $h * self::CAO / (self::CAO + 2 * self::TRAN)]
            : [0, 0, $w, $h];
        $cao = (int) round($rong * self::CAO / self::RONG);
        $nen = imagecreatetruecolor($rong, $cao);
        imagecopyresampled($nen, $goc, 0, 0, (int) round($x), (int) round($y), $rong, $cao, (int) round($cw), (int) round($ch));

        return $nen;
    }

    private function jpg(GdImage $nen, VoucherBatch $d, Voucher $v): string
    {
        $anh = imagecreatetruecolor(imagesx($nen), imagesy($nen));
        imagecopy($anh, $nen, 0, 0, 0, 0, imagesx($nen), imagesy($nen));
        $px = imagesx($anh) / self::RONG; // điểm ảnh / mm
        $o = self::oMa($d);
        [$x, $y, $w, $h] = [$o['trai'] * $px, $o['tren'] * $px, $o['rong'] * $px, $o['cao'] * $px];

        $trang = imagecolorallocate($anh, 255, 255, 255);
        $den = imagecolorallocate($anh, 17, 24, 39);
        $xam = imagecolorallocate($anh, 75, 85, 99);
        $bo = 3 * $px; // bo góc 3 mm
        imagefilledrectangle($anh, (int) ($x + $bo), (int) $y, (int) ($x + $w - $bo), (int) ($y + $h), $trang);
        imagefilledrectangle($anh, (int) $x, (int) ($y + $bo), (int) ($x + $w), (int) ($y + $h - $bo), $trang);
        foreach ([[$x + $bo, $y + $bo], [$x + $w - $bo, $y + $bo], [$x + $bo, $y + $h - $bo], [$x + $w - $bo, $y + $h - $bo]] as [$cx, $cy]) {
            imagefilledellipse($anh, (int) $cx, (int) $cy, (int) (2 * $bo), (int) (2 * $bo), $trang);
        }

        $chu = fn (string $s, float $co, float $tamY, string $phong, $mau) => $this->chuGiua($anh, $s, $co * $px, $x + $w / 2, $tamY, $phong, $mau);
        $dam = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');
        $mono = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSansMono-Bold.ttf');

        $canh = (int) round($o['qr'] * $px);
        $qr = imagecreatefromstring((new Writer(new GDLibRenderer($canh, 0, 'png', 9)))->writeString(self::urlKiemTra($v->code), Encoder::DEFAULT_BYTE_MODE_ECODING, ErrorCorrectionLevel::M()));
        imagecopy($anh, $qr, (int) round($x + $o['qrTrai'] * $px), (int) round($y + $o['qrTren'] * $px), 0, 0, imagesx($qr), imagesy($qr));

        $chu($v->code, self::coChuMa($o, $v->code), $y + ($o['maTren'] + 1.7) * $px, $mono, $den);
        if ($o['vach']) {
            $rongVach = MaVach128::doRong(Voucher::khoa($v->code));
            $mod = ($w - 4 * $px) / array_sum($rongVach);
            $vx = $x + 2 * $px;
            foreach ($rongVach as $i => $r) {
                if ($i % 2 === 0) {
                    imagefilledrectangle($anh, (int) round($vx), (int) round($y + $o['vachTren'] * $px), (int) round($vx + $r * $mod) - 1, (int) round($y + ($o['vachTren'] + 6.4) * $px), $den);
                }
                $vx += $r * $mod;
            }
        }
        $chu('Quét để kiểm tra · Số '.str_pad((string) $v->serial, 4, '0', STR_PAD_LEFT), 1.6, $y + ($o['chanTren'] + 1.3) * $px, $dam, $xam);

        ob_start();
        imagejpeg($anh, null, 90);

        return (string) ob_get_clean();
    }

    private function chuGiua(GdImage $anh, string $s, float $coPx, float $tamX, float $tamY, string $phong, int $mau): void
    {
        $co = $coPx * 0.75; // GD tính cỡ chữ theo point (96 dpi)
        $hop = imagettfbbox($co, 0, $phong, $s);
        $rong = $hop[2] - $hop[0];
        $cao = $hop[1] - $hop[7];
        imagettftext($anh, $co, 0, (int) round($tamX - $rong / 2), (int) round($tamY + $cao / 2), $mau, $phong, $s);
    }

    private function qrSvg(string $noiDung): string
    {
        $this->qr ??= new Writer(new ImageRenderer(new RendererStyle(240, 0), new SvgImageBackEnd));

        return $this->qr->writeString($noiDung, Encoder::DEFAULT_BYTE_MODE_ECODING, ErrorCorrectionLevel::M());
    }
}
