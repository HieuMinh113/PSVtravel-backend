<?php

namespace App\Services\ChamCong;

use App\Models\Attendance;
use App\Models\AttendanceFace;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Chấm công vào / ra: kiểm tra khuôn mặt (so 128 số đặc trưng trình duyệt gửi
 * lên với khuôn mặt đã đăng ký — so ở MÁY CHỦ, trình duyệt không biết khuôn mặt
 * gốc), vị trí so với công ty, đi trễ / về sớm. Trễ, về sớm, ngoài công ty thì
 * bắt buộc ghi lý do → chờ quản lý duyệt. Khuôn mặt không khớp vẫn cho chấm
 * nhưng đánh dấu nghi vấn.
 */
class ChamCong
{
    /**
     * @param  array{lat?: ?float, lng?: ?float, accuracy?: ?float, anh?: ?string, descriptor?: ?array, ly_do?: ?string}  $d
     * @return array{ok: bool, can_ly_do?: bool, vi_sao?: list<string>, loi?: string, thong_bao?: string, canh_bao?: list<string>}
     */
    public function cham(User $u, string $loai, array $d, ?Carbon $luc = null): array
    {
        $p = match ($loai) {
            'vao' => 'in',
            'ra' => 'out',
            default => throw new InvalidArgumentException('Loại chấm công không hợp lệ'),
        };
        $luc ??= now();
        $khuonMat = AttendanceFace::where('user_id', $u->id)->first();
        if (! $khuonMat) {
            return ['ok' => false, 'loi' => 'Bạn chưa đăng ký khuôn mặt.'];
        }
        $anh = self::docAnh($d['anh'] ?? null);
        if (! $anh) {
            return ['ok' => false, 'loi' => 'Không nhận được ảnh chụp. Cho phép trình duyệt dùng camera rồi thử lại.'];
        }

        $hom = Attendance::cuaNgay($u->id, $luc);
        if ($p === 'in' && $hom->in_at && $hom->in_source === 'cham') {
            return ['ok' => false, 'loi' => 'Hôm nay bạn đã chấm vào lúc '.$hom->in_at->format('H:i').'.'];
        }

        // Vị trí
        [$viTri, $cach, $doChinhXac] = self::viTri($d['lat'] ?? null, $d['lng'] ?? null, $d['accuracy'] ?? null);

        // Khuôn mặt: khoảng cách đặc trưng (null = trình duyệt không thấy mặt)
        $moTa = self::docDacTrung($d['descriptor'] ?? null);
        $khoang = $moTa ? self::soKhuonMat($moTa, $khuonMat->descriptor) : null;
        $khop = $khoang === null ? null : $khoang < Attendance::NGUONG_KHUON_MAT;

        // Cần lý do?
        $viSao = [];
        $lich = CauHinhChamCong::lichNgay($luc->copy()->startOfDay());
        $choPhep = CauHinhChamCong::phutChoPhep();
        if ($lich && $p === 'in' && $luc->gt($lich[0]->copy()->addMinutes($choPhep))) {
            $viSao[] = 'Đi trễ '.self::phut((int) $lich[0]->diffInMinutes($luc)).' (giờ vào '.$lich[0]->format('H:i').')';
        }
        if ($lich && $p === 'out' && $luc->lt($lich[1]->copy()->subMinutes($choPhep))) {
            $viSao[] = 'Về sớm '.self::phut((int) $luc->diffInMinutes($lich[1])).' (giờ ra '.$lich[1]->format('H:i').')';
        }
        if ($viTri === 'ngoai') {
            $viSao[] = 'Bạn đang cách công ty '.self::met($cach);
        } elseif ($viTri === 'khong_ro') {
            $viSao[] = $d['lat'] ?? null ? 'Vị trí không chính xác (sai số '.self::met($doChinhXac).')' : 'Không lấy được vị trí';
        }
        $lyDo = trim((string) ($d['ly_do'] ?? ''));
        if ($viSao && mb_strlen($lyDo) < 3) {
            return ['ok' => false, 'can_ly_do' => true, 'vi_sao' => $viSao];
        }

        DB::transaction(function () use ($hom, $p, $luc, $d, $viTri, $cach, $doChinhXac, $khoang, $khop, $anh, $u, $lyDo) {
            $cu = $hom->{"{$p}_photo"};
            $hom->forceFill([
                "{$p}_at" => $luc,
                "{$p}_source" => 'cham',
                "{$p}_lat" => isset($d['lat']) && is_numeric($d['lat']) ? (float) $d['lat'] : null,
                "{$p}_lng" => isset($d['lng']) && is_numeric($d['lng']) ? (float) $d['lng'] : null,
                "{$p}_accuracy" => $doChinhXac,
                "{$p}_distance" => $cach,
                "{$p}_location" => $viTri,
                "{$p}_photo" => $this->luuAnh($anh, $u, $luc, $p),
                "{$p}_face_distance" => $khoang,
                "{$p}_face_ok" => $khop,
                "{$p}_reason" => $lyDo !== '' ? mb_substr($lyDo, 0, 1000) : null,
            ]);
            if ($lyDo !== '') {
                $hom->forceFill(['review_status' => 'cho_duyet', 'reviewed_by' => null, 'reviewed_at' => null]);
            }
            $hom->save();
            $cu && Storage::disk(Attendance::DIA)->delete($cu); // chấm ra lại → bỏ ảnh cũ
        });

        $canhBao = [];
        if ($khop === false) {
            $canhBao[] = 'Khuôn mặt chưa khớp với ảnh đăng ký — quản lý sẽ xem lại.';
        } elseif ($khop === null) {
            $canhBao[] = 'Không nhận ra khuôn mặt trong ảnh — quản lý sẽ xem lại.';
        }

        return [
            'ok' => true,
            'thong_bao' => ($p === 'in' ? 'Đã chấm vào' : 'Đã chấm ra').' lúc '.$luc->format('H:i').($lyDo !== '' ? ' — lý do đã gửi quản lý duyệt.' : '.'),
            'canh_bao' => $canhBao,
        ];
    }

    /** Lần đầu: nhân viên đồng ý + chụp khuôn mặt mẫu. */
    public function dangKy(User $u, ?array $descriptor, ?string $anhDataUrl): AttendanceFace
    {
        if (AttendanceFace::where('user_id', $u->id)->exists()) {
            throw new InvalidArgumentException('Bạn đã đăng ký khuôn mặt. Muốn đăng ký lại, nhờ quản lý mở lại.');
        }
        $moTa = self::docDacTrung($descriptor);
        $anh = self::docAnh($anhDataUrl);
        if (! $moTa || ! $anh) {
            throw new InvalidArgumentException('Chưa nhận ra khuôn mặt. Nhìn thẳng camera, đủ sáng, bỏ khẩu trang rồi chụp lại.');
        }

        return AttendanceFace::create([
            'user_id' => $u->id,
            'descriptor' => $moTa,
            'photo' => $this->luuAnh($anh, $u, now(), 'dang-ky'),
            'consented_at' => now(),
        ]);
    }

    /** Quên chấm: xin bổ sung giờ vào / ra cho một ngày → chờ quản lý duyệt. */
    public function boSung(User $u, Carbon $ngay, string $loai, string $gio, string $lyDo): Attendance
    {
        $p = $loai === 'vao' ? 'in' : 'out';
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', $gio, $m) || $ngay->isFuture() || $ngay->lt(today()->subDays(31))) {
            throw new InvalidArgumentException('Ngày / giờ bổ sung không hợp lệ.');
        }
        $hom = Attendance::cuaNgay($u->id, $ngay);
        if ($hom->{"{$p}_at"} && $hom->{"{$p}_source"} === 'cham') {
            throw new InvalidArgumentException('Ngày này đã có giờ '.($p === 'in' ? 'vào' : 'ra').' chấm bằng khuôn mặt, không bổ sung được.');
        }
        $hom->forceFill([
            "{$p}_at" => $ngay->copy()->setTime((int) $m[1], (int) $m[2]),
            "{$p}_source" => 'bo_sung',
            "{$p}_reason" => 'Bổ sung công: '.mb_substr(trim($lyDo), 0, 1000),
            'review_status' => 'cho_duyet',
            'reviewed_by' => null,
            'reviewed_at' => null,
        ])->save();

        return $hom;
    }

    public function duyet(Attendance $a, bool $chapNhan, ?string $ghiChu, User $nguoi): void
    {
        $a->forceFill([
            'review_status' => $chapNhan ? 'chap_nhan' : 'tu_choi',
            'reviewed_by' => $nguoi->id,
            'reviewed_at' => now(),
            'review_note' => filled($ghiChu) ? mb_substr(trim($ghiChu), 0, 1000) : null,
        ])->save();
    }

    /** @return array{0: ?string, 1: ?int, 2: ?int} [trong|ngoai|khong_ro|null, cách (m), sai số (m)] */
    public static function viTri($lat, $lng, $saiSo): array
    {
        $congTy = CauHinhChamCong::toaDo();
        $saiSo = is_numeric($saiSo) ? (int) round($saiSo) : null;
        if (! is_numeric($lat) || ! is_numeric($lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
            return [$congTy ? 'khong_ro' : null, null, $saiSo];
        }
        if (! $congTy) {
            return [null, null, $saiSo]; // chưa đặt toạ độ công ty → không xét
        }
        $cach = CauHinhChamCong::khoangCach((float) $lat, (float) $lng, $congTy[0], $congTy[1]);
        $loai = match (true) {
            $cach <= CauHinhChamCong::banKinh() => 'trong',
            $saiSo !== null && $saiSo > 500 => 'khong_ro', // máy tính đoán vị trí qua mạng, lệch xa
            default => 'ngoai',
        };

        return [$loai, $cach, $saiSo];
    }

    public static function soKhuonMat(array $a, array $b): float
    {
        $tong = 0.0;
        foreach ($a as $i => $v) {
            $tong += ((float) $v - (float) ($b[$i] ?? 0)) ** 2;
        }

        return round(sqrt($tong), 3);
    }

    /** 128 số thực trong khoảng hợp lý, không thì bỏ. @return list<float>|null */
    public static function docDacTrung($ds): ?array
    {
        if (! is_array($ds) || count($ds) !== 128) {
            return null;
        }
        $ra = [];
        foreach (array_values($ds) as $v) {
            if (! is_numeric($v) || abs((float) $v) > 2) {
                return null;
            }
            $ra[] = round((float) $v, 6);
        }

        return $ra;
    }

    /** Ảnh JPEG từ camera (data URL), tối đa 1MB. */
    public static function docAnh(?string $dataUrl): ?string
    {
        if (! $dataUrl || ! preg_match('#^data:image/jpeg;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) {
            return null;
        }
        $nhi = base64_decode($m[1], true);
        if ($nhi === false || strlen($nhi) > 1024 * 1024 || strlen($nhi) < 500) {
            return null;
        }
        $kieu = @getimagesizefromstring($nhi);

        return $kieu && ($kieu['mime'] ?? null) === 'image/jpeg' ? $nhi : null;
    }

    private function luuAnh(string $nhi, User $u, Carbon $luc, string $nhan): string
    {
        $duong = 'cham-cong/'.$luc->format('Y-m').'/'.$u->id.'-'.$luc->format('Ymd-His').'-'.$nhan.'-'.Str::random(6).'.jpg';
        Storage::disk(Attendance::DIA)->put($duong, $nhi);

        return $duong;
    }

    private static function phut(int $p): string
    {
        return $p >= 60 ? intdiv($p, 60).' giờ '.($p % 60 ? ($p % 60).' phút' : '') : $p.' phút';
    }

    private static function met(?int $m): string
    {
        return $m === null ? '?' : ($m >= 1000 ? number_format($m / 1000, 1, ',', '.').' km' : $m.' m');
    }
}
