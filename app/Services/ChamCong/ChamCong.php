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
 * bắt buộc ghi lý do → chờ quản lý duyệt. Chấm ở ngoài công ty / không lấy được
 * vị trí thì lượt đó chỉ tính công khi quản lý chấp nhận; chấm vào ở ngoài rồi
 * thì tới công ty chấm vào lại được (lấy giờ mới, bỏ chờ duyệt).
 *
 * Trang chấm công quét liên tục và gửi kèm chi_khi_khop: khớp mới chấm. Khi
 * quét mãi không khớp (khẩu trang, ảnh đăng ký xấu…) nhân viên bấm "Gửi ảnh cho
 * quản lý" → chấm không cần khớp, đánh dấu nghi vấn để quản lý xem lại.
 */
class ChamCong
{
    /**
     * Chỉ so khuôn mặt, không lưu gì — trang chấm công gọi trong lúc quét (gửi
     * 128 số, không kèm ảnh nên mạng yếu vẫn nhanh). Khớp rồi trình duyệt mới
     * gửi ảnh + vị trí để chấm; lúc đó cham() so lại lần nữa.
     *
     * @return bool|null null = chưa đăng ký khuôn mặt
     */
    public function khopKhuonMat(User $u, $descriptor): ?bool
    {
        $khuonMat = AttendanceFace::where('user_id', $u->id)->first();
        if (! $khuonMat) {
            return null;
        }
        $moTa = self::docDacTrung($descriptor);

        return $moTa !== null && self::khoangGanNhat($moTa, $khuonMat) < Attendance::NGUONG_KHUON_MAT;
    }

    /** Độ lệch nhỏ nhất so với mẫu đăng ký và các mẫu thêm theo loại máy. */
    public static function khoangGanNhat(array $moTa, AttendanceFace $khuonMat): float
    {
        return min(array_map(fn (array $mau) => self::soKhuonMat($moTa, $mau), $khuonMat->cacMau()));
    }

    /**
     * @param  array{lat?: ?float, lng?: ?float, accuracy?: ?float, anh?: ?string, descriptor?: ?array, ly_do?: ?string, chi_khi_khop?: bool, thiet_bi?: ?string, vi_tri_loi?: ?string, thoi_gian_quet?: ?int}  $d
     * @return array{ok: bool, khong_khop?: bool, can_ly_do?: bool, vi_sao?: list<string>, loi?: string, thong_bao?: string, canh_bao?: list<string>}
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
        $hom = Attendance::cuaNgay($u->id, $luc);
        if ($p === 'in' && $hom->in_at && $hom->in_source === 'cham' && ! $hom->duocChamVaoLai()) {
            return ['ok' => false, 'loi' => 'Hôm nay bạn đã chấm vào lúc '.$hom->in_at->format('H:i').'.'];
        }
        $chamLai = $hom->{"{$p}_at"} && $hom->{"{$p}_source"} === 'cham'; // thay lượt cũ của phần này

        // Khuôn mặt: khoảng cách đặc trưng tới mẫu gần nhất (null = trình duyệt không thấy mặt)
        $moTa = self::docDacTrung($d['descriptor'] ?? null);
        $khoang = $moTa ? self::khoangGanNhat($moTa, $khuonMat) : null;
        $khop = $khoang === null ? null : $khoang < Attendance::NGUONG_KHUON_MAT;
        $thietBi = array_key_exists((string) ($d['thiet_bi'] ?? ''), Attendance::THIET_BI) ? $d['thiet_bi'] : null;

        // Quét tự động: chỉ chấm khi khuôn mặt khớp. Chưa khớp thì không lưu gì,
        // trình duyệt quét tiếp. Không trả khoảng cách — khỏi dò dẫm từng số.
        if (! empty($d['chi_khi_khop']) && $khop !== true) {
            return ['ok' => false, 'khong_khop' => true];
        }

        $anh = self::docAnh($d['anh'] ?? null);
        if (! $anh) {
            return ['ok' => false, 'loi' => 'Không nhận được ảnh chụp. Cho phép trình duyệt dùng camera rồi thử lại.'];
        }

        // Vị trí + vì sao không có / không dùng được (để quản lý biết, khỏi đoán)
        [$viTri, $cach, $doChinhXac] = self::viTri($d['lat'] ?? null, $d['lng'] ?? null, $d['accuracy'] ?? null);
        $viTriLoi = self::viTriLoi($d, $viTri);
        $giay = is_numeric($d['thoi_gian_quet'] ?? null) ? max(0, min(3_600_000, (int) $d['thoi_gian_quet'])) : null;

        // Cần lý do? Trễ quá số phút phải ghi lý do / về sớm → lý do phải được
        // quản lý duyệt. Trễ ít hơn thì vẫn tính phút trễ, không hỏi. Đúng giờ mà
        // ngoài công ty / không rõ vị trí → chỉ ghi lý do, KHÔNG cần duyệt.
        // Tính theo phút như giờ hiển thị (08:31:45 là 08:31).
        $viSao = [];
        $canDuyet = false;
        $lich = CauHinhChamCong::lichNgay($luc->copy()->startOfDay());
        $tre = $lich && $p === 'in' ? CauHinhChamCong::phutTre($luc, $lich[0]) : 0;
        $som = $lich && $p === 'out' ? CauHinhChamCong::phutSom($luc, $lich[1]) : 0;
        if ($tre > CauHinhChamCong::phutCanLyDo()) {
            $viSao[] = 'Đi trễ '.self::phut($tre).' (giờ vào '.$lich[0]->format('H:i').')';
            $canDuyet = true;
        }
        if ($som > 0) {
            // Về sớm: lý do chưa được chấp nhận thì ngày này chỉ được nửa công
            $viSao[] = 'Về sớm '.self::phut($som).' (giờ ra '.$lich[1]->format('H:i').') — quản lý chưa duyệt lý do thì ngày này tính '
                .($luc->isSaturday() ? '0,25' : '0,5').' công';
            $canDuyet = true;
        }
        // Ở ngoài công ty hoặc không lấy được vị trí (chưa cho phép, GPS lỗi…):
        // lượt này phải được quản lý chấp nhận mới tính công. Sai số lớn (GPS
        // yếu trong toà nhà) thì chỉ ghi lý do như trước.
        $ngoaiCanDuyet = $viTri === 'ngoai' || ($viTri === 'khong_ro' && $viTriLoi !== 'sai_so_lon');
        $phaiDuyet = ' — chấm ở ngoài công ty phải được quản lý duyệt mới tính công, không duyệt thì cả ngày 0 công'
            .($p === 'in' ? '. Sắp tới công ty thì bấm Huỷ, tới nơi chấm lại' : '');
        if ($viTri === 'ngoai') {
            $viSao[] = 'Bạn đang cách công ty '.self::met($cach).$phaiDuyet;
        } elseif ($viTri === 'khong_ro') {
            $viSao[] = $viTriLoi === 'sai_so_lon'
                ? 'Vị trí không chính xác (sai số '.self::met($doChinhXac).')'
                : 'Không lấy được vị trí'.($viTriLoi ? ' — '.Attendance::VI_TRI_LOI[$viTriLoi] : '').$phaiDuyet;
        }
        $canDuyet = $canDuyet || $ngoaiCanDuyet;
        $lyDo = trim((string) ($d['ly_do'] ?? ''));
        if ($viSao && mb_strlen($lyDo) < 3) {
            return ['ok' => false, 'can_ly_do' => true, 'vi_sao' => $viSao];
        }

        DB::transaction(function () use ($hom, $p, $luc, $d, $viTri, $viTriLoi, $cach, $doChinhXac, $khoang, $khop, $anh, $u, $lyDo, $canDuyet, $ngoaiCanDuyet, $chamLai, $thietBi, $giay, $moTa, $khuonMat) {
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
                "{$p}_device" => $thietBi,
                "{$p}_location_error" => $viTriLoi,
                "{$p}_requires_approval" => $ngoaiCanDuyet,
                "{$p}_scan_ms" => $giay,
            ]);
            if ($canDuyet) {
                $hom->forceFill(['review_status' => 'cho_duyet', 'reviewed_by' => null, 'reviewed_at' => null]);
            } elseif ($chamLai && in_array($hom->review_status, ['cho_duyet', 'tu_choi'], true)) {
                // Chấm lại ở công ty thay lượt cũ (ở ngoài / về sớm…): ngày này hết
                // gì phải duyệt thì bỏ trạng thái chờ duyệt / không chấp nhận cũ
                $hom->tinhPhut($p === 'in', $p === 'out');
                if (! $hom->conCanDuyet()) {
                    $hom->forceFill(['review_status' => null, 'reviewed_by' => null, 'reviewed_at' => null, 'review_note' => null]);
                }
            }
            $hom->save();
            $cu && Storage::disk(Attendance::DIA)->delete($cu); // chấm ra lại → bỏ ảnh cũ
            if ($khop) {
                $this->luuMauTheoMay($khuonMat, $moTa, $thietBi, $luc);
            }
        });

        $canhBao = [];
        if ($p === 'out' && ! $hom->in_at) {
            // Quên chấm vào: báo ngay để kịp xin bổ sung, khỏi tới cuối tháng mới biết
            $canhBao[] = 'Hôm nay bạn chưa chấm vào — ngày này chỉ tính '.($luc->isSaturday() ? '0,25' : '0,5')
                .' công. Bấm "Xin bổ sung công" để bổ sung giờ vào cho quản lý duyệt.';
        }
        if ($khop === false) {
            $canhBao[] = 'Khuôn mặt chưa khớp với ảnh đăng ký — quản lý sẽ xem lại.';
        } elseif ($khop === null) {
            $canhBao[] = 'Không nhận ra khuôn mặt trong ảnh — quản lý sẽ xem lại.';
        }

        return [
            'ok' => true,
            'thong_bao' => ($p === 'in' ? 'Đã chấm vào' : 'Đã chấm ra').' lúc '.$luc->format('H:i').match (true) {
                $canDuyet => ' — lý do đã gửi quản lý duyệt.'.($ngoaiCanDuyet && $p === 'in'
                    ? ' Tới công ty thì bấm "Chấm vào lại tại công ty" để được tính ngay, khỏi chờ duyệt.'
                    : ''),
                $tre > 0 => ' — trễ '.self::phut($tre).'.',
                $lyDo !== '' => ' — đã ghi lý do.',
                default => '.',
            },
            'canh_bao' => $canhBao,
        ];
    }

    /**
     * Khớp lần đầu trên một loại máy khác lúc đăng ký (VD đăng ký bằng điện thoại,
     * nay quét webcam máy tính) → lưu thêm mẫu này, lần sau so nhanh / ổn định hơn.
     * Chỉ lưu khi khớp với CHÍNH mẫu đăng ký (không nối mẫu thêm vào mẫu thêm), mỗi
     * loại máy một mẫu. Quản lý "Cho đăng ký lại" thì mẫu thêm mất theo.
     */
    private function luuMauTheoMay(AttendanceFace $khuonMat, array $moTa, ?string $thietBi, Carbon $luc): void
    {
        $them = (array) $khuonMat->extra_descriptors;
        if (! $thietBi || $thietBi === $khuonMat->device || isset($them[$thietBi])
            || self::soKhuonMat($moTa, $khuonMat->descriptor) >= Attendance::NGUONG_KHUON_MAT) {
            return;
        }
        $them[$thietBi] = ['descriptor' => $moTa, 'at' => $luc->toIso8601String()];
        $khuonMat->forceFill(['extra_descriptors' => $them])->save();
    }

    /** Vì sao không có vị trí: trình duyệt báo (chưa cho phép / chờ lâu…), hoặc sai số quá lớn. */
    private static function viTriLoi(array $d, ?string $viTri): ?string
    {
        if (is_numeric($d['lat'] ?? null) && is_numeric($d['lng'] ?? null)) {
            return $viTri === 'khong_ro' ? 'sai_so_lon' : null;
        }
        $ma = (string) ($d['vi_tri_loi'] ?? '');

        // Trình duyệt không nói (trang cũ còn lưu tạm) / mã lạ → để trống, không đoán
        return $ma !== 'sai_so_lon' && array_key_exists($ma, Attendance::VI_TRI_LOI) ? $ma : null;
    }

    /** Lần đầu: nhân viên đồng ý + chụp khuôn mặt mẫu. */
    public function dangKy(User $u, ?array $descriptor, ?string $anhDataUrl, ?string $thietBi = null): AttendanceFace
    {
        if (AttendanceFace::where('user_id', $u->id)->exists()) {
            throw new InvalidArgumentException('Bạn đã đăng ký khuôn mặt. Muốn đăng ký lại, nhờ quản lý mở lại.');
        }
        $moTa = self::docDacTrung($descriptor);
        $anh = self::docAnh($anhDataUrl);
        if (! $moTa || ! $anh) {
            throw new InvalidArgumentException('Chưa nhận ra khuôn mặt. Nhìn thẳng camera, đủ sáng, bỏ khẩu trang rồi quét lại.');
        }

        return AttendanceFace::create([
            'user_id' => $u->id,
            'descriptor' => $moTa,
            'photo' => $this->luuAnh($anh, $u, now(), 'dang-ky'),
            'consented_at' => now(),
            'device' => array_key_exists((string) $thietBi, Attendance::THIET_BI) ? $thietBi : null,
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
        $banKinh = CauHinhChamCong::banKinh();
        $loai = match (true) {
            // Điểm rơi trong bán kính nhưng sai số còn lớn hơn bán kính (GPS trong
            // nhà lệch) → không dám nói là ở công ty, hỏi lý do
            $cach <= $banKinh => $saiSo !== null && $saiSo > $banKinh ? 'khong_ro' : 'trong',
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
