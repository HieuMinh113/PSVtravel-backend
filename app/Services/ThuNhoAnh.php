<?php

namespace App\Services;

use GdImage;

/**
 * Thu nhỏ ảnh nhân viên chèn vào nội dung bài (ảnh chụp điện thoại / máy ảnh
 * 4000–6000px, 3–6 MB). Website đã cho ảnh trong bài đi qua bộ tối ưu của Next,
 * nhưng bản gốc quá to vẫn tốn dung lượng máy chủ và máy chủ phải giải nén cả
 * ảnh 24 megapixel mỗi lần tạo bản thu nhỏ.
 *
 * Giữ nguyên tên tệp và định dạng (đường dẫn trong bài không đổi): rộng quá
 * 2000px thì thu về 2000px; JPEG không rộng nhưng nặng quá 1 MB thì nén lại.
 * Chỉ ghi đè khi bản mới nhẹ hơn hẳn.
 */
class ThuNhoAnh
{
    public const RONG_TOI_DA = 2000;

    public const JPEG_NANG = 1_000_000;

    public const CHAT_LUONG_JPEG = 82;

    /**
     * @return array{truoc: int, sau: int, rong_truoc: int, rong_sau: int}|null
     *                                                                          null = không cần thu nhỏ, hoặc không đọc được ảnh
     */
    public static function xuLy(string $tep, bool $ghi = true, ?string $saoLuuVao = null): ?array
    {
        $thongTin = @getimagesize($tep);
        if (! $thongTin) {
            return null;
        }
        [$rong, $cao, $loai] = $thongTin;
        $truoc = (int) filesize($tep);

        $laJpeg = $loai === IMAGETYPE_JPEG;
        $laPng = $loai === IMAGETYPE_PNG;
        $laWebp = $loai === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp');
        if (! $laJpeg && ! $laPng && ! $laWebp) {
            return null; // GIF (có thể là ảnh động), định dạng lạ: để nguyên
        }

        // Ảnh điện thoại chụp dọc lưu xoay bằng thẻ EXIF; GD bỏ thẻ đó khi ghi lại
        // nên phải xoay thật điểm ảnh trước, kẻo ảnh hiện nằm ngang
        $huong = $laJpeg ? self::huongExif($tep) : 1;
        $rongThat = in_array($huong, [5, 6, 7, 8], true) ? $cao : $rong;
        $canThuNho = $rongThat > self::RONG_TOI_DA;
        if (! $canThuNho && ! ($laJpeg && $truoc > self::JPEG_NANG)) {
            return null;
        }

        $anh = match (true) {
            $laJpeg => @imagecreatefromjpeg($tep),
            $laPng => @imagecreatefrompng($tep),
            default => @imagecreatefromwebp($tep),
        };
        if (! $anh) {
            return null;
        }
        $anh = self::xoayTheoExif($anh, $huong);

        if ($canThuNho) {
            $rongMoi = self::RONG_TOI_DA;
            $caoMoi = (int) round(imagesy($anh) * $rongMoi / imagesx($anh));
            $nho = imagecreatetruecolor($rongMoi, $caoMoi);
            if (! $laJpeg) {
                imagealphablending($nho, false);
                imagesavealpha($nho, true);
            }
            imagecopyresampled($nho, $anh, 0, 0, 0, 0, $rongMoi, $caoMoi, imagesx($anh), imagesy($anh));
            imagedestroy($anh);
            $anh = $nho;
        }

        ob_start();
        match (true) {
            $laJpeg => imagejpeg($anh, null, self::CHAT_LUONG_JPEG),
            $laPng => imagepng($anh, null, 9),
            default => imagewebp($anh, null, self::CHAT_LUONG_JPEG),
        };
        $duLieu = (string) ob_get_clean();
        $rongSau = imagesx($anh);
        imagedestroy($anh);

        // Không nhẹ đi đáng kể (PNG đồ hoạ đã nén sẵn…) thì giữ bản gốc
        $sau = strlen($duLieu);
        if ($duLieu === '' || $sau > $truoc * 0.9) {
            return null;
        }

        if ($ghi) {
            if ($saoLuuVao !== null && ! is_file($saoLuuVao)) {
                @mkdir(dirname($saoLuuVao), 0775, true);
                copy($tep, $saoLuuVao);
            }
            // Ghi ra tệp tạm rồi đổi tên: đang ghi dở mà lỗi thì ảnh gốc vẫn còn
            $tam = $tep.'.thu-nho';
            file_put_contents($tam, $duLieu);
            rename($tam, $tep);
            clearstatcache(true, $tep);
        }

        return ['truoc' => $truoc, 'sau' => $sau, 'rong_truoc' => $rongThat, 'rong_sau' => $rongSau];
    }

    /**
     * Đường dẫn (trên ổ public) của ảnh nằm trong nội dung HTML:
     * ".../storage/guides/noi-dung/abc.jpg" → "guides/noi-dung/abc.jpg"
     *
     * @return list<string>
     */
    public static function anhTrongNoiDung(?string $html): array
    {
        preg_match_all('#/storage/([A-Za-z0-9_\-]+/noi-dung/[A-Za-z0-9_\-]+\.(?:jpe?g|png|webp))#i', (string) $html, $khop);

        return array_values(array_unique($khop[1]));
    }

    /** Thẻ Orientation (0x0112) trong EXIF của JPEG; 1 = đúng chiều. Không cần extension exif. */
    public static function huongExif(string $tep): int
    {
        $f = @fopen($tep, 'rb');
        if (! $f) {
            return 1;
        }
        try {
            if (fread($f, 2) !== "\xFF\xD8") {
                return 1;
            }
            while (! feof($f)) {
                $dau = fread($f, 4);
                if (strlen($dau) < 4 || $dau[0] !== "\xFF") {
                    return 1;
                }
                $ma = ord($dau[1]);
                $dai = unpack('n', substr($dau, 2))[1] - 2;
                if ($ma === 0xDA || $dai < 0) {
                    return 1; // tới phần dữ liệu ảnh: không có EXIF
                }
                $doan = $dai > 0 ? fread($f, $dai) : '';
                if ($ma !== 0xE1 || ! str_starts_with($doan, "Exif\0\0")) {
                    continue;
                }
                $tiff = substr($doan, 6);
                $le = substr($tiff, 0, 2) === 'II';
                $u16 = fn (int $o) => unpack($le ? 'v' : 'n', substr($tiff, $o, 2))[1] ?? 0;
                $u32 = fn (int $o) => unpack($le ? 'V' : 'N', substr($tiff, $o, 4))[1] ?? 0;
                if (strlen($tiff) < 8) {
                    return 1;
                }
                $ifd = $u32(4);
                $soMuc = $u16($ifd);
                for ($i = 0; $i < $soMuc; $i++) {
                    $o = $ifd + 2 + $i * 12;
                    if ($o + 12 > strlen($tiff)) {
                        break;
                    }
                    if ($u16($o) === 0x0112) {
                        $h = $u16($o + 8);

                        return $h >= 1 && $h <= 8 ? $h : 1;
                    }
                }

                return 1;
            }

            return 1;
        } finally {
            fclose($f);
        }
    }

    private static function xoayTheoExif(GdImage $anh, int $huong): GdImage
    {
        if ($huong === 1) {
            return $anh;
        }
        if (in_array($huong, [2, 4, 5, 7], true)) {
            imageflip($anh, $huong === 4 ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
        }
        // Góc của imagerotate tính ngược chiều kim đồng hồ
        $goc = match ($huong) {
            3 => 180,
            5, 8 => 90,
            6, 7 => -90,
            default => 0,
        };
        if ($goc === 0) {
            return $anh;
        }
        $xoay = imagerotate($anh, $goc, 0);
        if ($xoay === false) {
            return $anh;
        }
        imagedestroy($anh);

        return $xoay;
    }
}
