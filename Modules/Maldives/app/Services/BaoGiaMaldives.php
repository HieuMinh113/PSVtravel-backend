<?php

namespace Modules\Maldives\Services;

use Modules\Maldives\Models\MaldivesCombo;
use Modules\Maldives\Models\MaldivesComboDate;
use Modules\Page\Models\Setting;

/**
 * Giá tạm tính một đơn combo Maldives — đúng cách bảng giá đối tác ghi:
 *  - giá USD cho 1 người lớn, theo hạng phòng và ngày khởi hành;
 *  - trẻ em tính theo % giá người lớn (VD 75%);
 *  - phụ thu theo nơi khởi hành, tính cho mỗi khách (người lớn + trẻ em);
 *  - em bé dưới 2 tuổi: chưa có giá → nhân viên báo sau.
 * Phần nào chưa có số thì KHÔNG cộng vào và ghi rõ trong "chua_gom".
 * Website tính lại y hệt (app/lib/maldives.js) để khách thấy cùng con số.
 *
 * @phpstan-type BaoGia array{don_gia_usd: ?int, gia_tre_em_usd: ?int, phu_thu_usd: ?int, tong_usd: ?int, ty_gia: ?int, tong_vnd: ?int, chua_gom: list<string>}
 */
class BaoGiaMaldives
{
    /** @return BaoGia */
    public static function tinh(MaldivesCombo $combo, ?MaldivesComboDate $ngay, ?int $hangPhong, ?string $noiDi, int $nguoiLon, int $treEm = 0, int $emBe = 0): array
    {
        $chuaGom = [];
        $donGia = $ngay && $hangPhong ? $ngay->gia($hangPhong) : null;

        $giaTreEm = $donGia !== null && $combo->child_percent ? (int) round($donGia * $combo->child_percent / 100) : null;
        if ($treEm > 0 && $giaTreEm === null && $donGia !== null) {
            $chuaGom[] = 'giá trẻ em';
        }

        $phuThu = 0;
        if (filled($noiDi)) {
            $n = $combo->noiKhoiHanh($noiDi);
            $phuThu = $n && is_numeric($n['surcharge_usd'] ?? null) ? (int) $n['surcharge_usd'] : null;
            if ($phuThu === null) {
                $chuaGom[] = 'phụ thu khởi hành từ '.$noiDi;
            }
        }
        if ($emBe > 0) {
            $chuaGom[] = 'em bé dưới 2 tuổi';
        }

        $tong = null;
        if ($donGia !== null) {
            $tong = $nguoiLon * $donGia
                + ($giaTreEm !== null ? $treEm * $giaTreEm : 0)
                + ($phuThu ?? 0) * ($nguoiLon + $treEm);
        }

        $tyGia = (int) preg_replace('/\D/', '', (string) Setting::lay('maldives_ty_gia')) ?: null;

        return [
            'don_gia_usd' => $donGia,
            'gia_tre_em_usd' => $giaTreEm,
            'phu_thu_usd' => $phuThu,
            'tong_usd' => $tong,
            'ty_gia' => $tyGia,
            'tong_vnd' => $tong !== null && $tyGia ? $tong * $tyGia : null,
            'chua_gom' => $chuaGom,
        ];
    }
}
