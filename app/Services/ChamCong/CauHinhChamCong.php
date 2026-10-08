<?php

namespace App\Services\ChamCong;

use Illuminate\Support\Carbon;
use Modules\Page\Models\Setting;

/**
 * Cấu hình chấm công lưu ở bảng settings (nhóm cham_cong), sửa ở trang
 * "Cấu hình chấm công". Giá trị sai / trống thì dùng mặc định.
 */
class CauHinhChamCong
{
    public const MAC_DINH = [
        'cham_cong_ban_kinh' => '150',
        'cham_cong_vao' => '08:00',
        'cham_cong_ra' => '17:30',
        'cham_cong_vao_t7' => '08:00',
        'cham_cong_ra_t7' => '12:00',
        'cham_cong_phut_tre' => '5',
    ];

    public static function lay(string $khoa): ?string
    {
        $v = Setting::lay($khoa);

        return filled($v) ? trim($v) : (self::MAC_DINH[$khoa] ?? null);
    }

    /** @return array{0: float, 1: float}|null toạ độ công ty, null = chưa đặt */
    public static function toaDo(): ?array
    {
        $lat = self::lay('cham_cong_lat');
        $lng = self::lay('cham_cong_lng');
        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        return [(float) $lat, (float) $lng];
    }

    public static function banKinh(): int
    {
        return max(20, (int) self::lay('cham_cong_ban_kinh'));
    }

    public static function phutChoPhep(): int
    {
        return max(0, (int) self::lay('cham_cong_phut_tre'));
    }

    /**
     * Giờ vào / ra của một ngày. Chủ nhật → null (không tính trễ / sớm).
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    public static function lichNgay(Carbon $ngay): ?array
    {
        if ($ngay->isSunday()) {
            return null;
        }
        $t7 = $ngay->isSaturday();
        $gio = fn (string $khoa, string $macDinh) => preg_match('/^(\d{1,2}):(\d{2})$/', (string) self::lay($khoa), $m)
            ? $ngay->copy()->setTime((int) $m[1], (int) $m[2])
            : $ngay->copy()->setTimeFromTimeString($macDinh);

        return [
            $gio($t7 ? 'cham_cong_vao_t7' : 'cham_cong_vao', $t7 ? '08:00' : '08:00'),
            $gio($t7 ? 'cham_cong_ra_t7' : 'cham_cong_ra', $t7 ? '12:00' : '17:30'),
        ];
    }

    /** Khoảng cách 2 toạ độ (mét) — công thức haversine. */
    public static function khoangCach(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return (int) round($r * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
