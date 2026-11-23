<?php

namespace App\Mcp\Tools\NoiBo;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Phần dùng chung của các công cụ XEM số liệu quản trị trong MCP nội bộ.
 *
 * Dữ liệu trả về đi tới máy chủ của hãng AI, nên: số điện thoại / email khách
 * luôn che bớt; không trả số hộ chiếu, ngày sinh, tệp giấy tờ, ảnh, vị trí.
 * Mỗi kết quả kèm link mở đúng trang đó trong admin để xem đầy đủ.
 */
class QuanTri
{
    /**
     * Kiểm tra quyền theo đúng phân quyền trong admin (Hệ thống → Vai trò).
     * $quyen là tên hành động của policy ("viewAny", "view"…) khi có $doiTuong,
     * hoặc tên quyền ("ViewAll:Attendance") khi không có. null = được phép.
     */
    public static function thieuQuyen(?User $u, string $quyen, string $viec, mixed $doiTuong = null): ?string
    {
        $duoc = $doiTuong === null ? $u?->can($quyen) : $u?->can($quyen, $doiTuong);

        return $duoc ? null : "Tài khoản của bạn chưa có quyền {$viec} trong admin. Nhờ người quản trị tick quyền ở Hệ thống → Vai trò.";
    }

    /** "0901 234 567" → "090****567" */
    public static function sdt(?string $so): ?string
    {
        $chuSo = preg_replace('/\D/', '', (string) $so);
        if ($chuSo === '') {
            return null;
        }

        return strlen($chuSo) < 7 ? '***' : substr($chuSo, 0, 3).'****'.substr($chuSo, -3);
    }

    /** "nguyenvana@gmail.com" → "n***@gmail.com" */
    public static function email(?string $email): ?string
    {
        if (blank($email) || ! str_contains($email, '@')) {
            return blank($email) ? null : '***';
        }
        [$ten, $mien] = explode('@', $email, 2);

        return mb_substr($ten, 0, 1).'***@'.$mien;
    }

    public static function ngay(?CarbonInterface $d): ?string
    {
        return $d?->format('Y-m-d');
    }

    public static function gio(?CarbonInterface $d): ?string
    {
        return $d?->format('Y-m-d H:i');
    }

    /**
     * Tìm theo từ khoá: khớp mã, tên (không phân biệt hoa thường) hoặc các chữ số
     * của số điện thoại ("0901234567" khớp "0901 234 567").
     *
     * @param  list<string>  $cotChu
     */
    public static function timTuKhoa(Builder $q, ?string $tuKhoa, array $cotChu, ?string $cotSdt = null): Builder
    {
        $tuKhoa = trim((string) $tuKhoa);
        if ($tuKhoa === '') {
            return $q;
        }
        $chuSo = preg_replace('/\D/', '', $tuKhoa);
        $mau = '%'.mb_strtolower(addcslashes($tuKhoa, '%_\\')).'%';

        return $q->where(function (Builder $w) use ($cotChu, $cotSdt, $mau, $chuSo) {
            foreach ($cotChu as $cot) {
                $w->orWhereRaw('lower('.$cot.') like ?', [$mau]);
            }
            if ($cotSdt && strlen($chuSo) >= 4) {
                $w->orWhereRaw("replace(replace(replace({$cotSdt}, ' ', ''), '.', ''), '-', '') like ?", ['%'.$chuSo.'%']);
            }
        });
    }

    /** Luật kiểm tra tháng "YYYY-MM" */
    public const LUAT_THANG = ['nullable', 'date_format:Y-m'];

    public const THONG_BAO = [
        'thang.date_format' => 'thang phải có dạng YYYY-MM, ví dụ 2026-11.',
        'date_format' => 'Ngày phải có dạng YYYY-MM-DD.',
    ];
}
