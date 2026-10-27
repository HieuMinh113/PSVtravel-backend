<?php

namespace Modules\Maldives\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Quyền mục Maldives theo bộ phận. Chỉ THÊM quyền, chạy lại bao nhiêu lần cũng được.
 * Gọi từ migration (máy chủ thật có ngay khi triển khai) và từ RoleSeeder.
 */
class QuyenMaldivesSeeder extends Seeder
{
    private const HANH_DONG = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny', 'Restore', 'RestoreAny',
        'ForceDelete', 'ForceDeleteAny', 'Replicate', 'Reorder'];

    private const MUC = ['MaldivesResort', 'MaldivesCombo', 'MaldivesBooking'];

    /** Xử lý đơn: xem, tạo, sửa (nhận đơn, đổi trạng thái, ghi đã thu) */
    public const DON = ['ViewAny:MaldivesBooking', 'View:MaldivesBooking', 'Create:MaldivesBooking', 'Update:MaldivesBooking'];

    public const XEM_COMBO = ['ViewAny:MaldivesCombo', 'View:MaldivesCombo', 'ViewAny:MaldivesResort', 'View:MaldivesResort'];

    /** Nhập / sửa combo, resort (không xoá) */
    public const SUA_COMBO = [...self::XEM_COMBO, 'Create:MaldivesCombo', 'Update:MaldivesCombo', 'Create:MaldivesResort', 'Update:MaldivesResort'];

    /** @return array<string, list<string>> vai trò → quyền */
    public static function theoVaiTro(): array
    {
        $tatCa = [];
        foreach (self::MUC as $m) {
            foreach (self::HANH_DONG as $h) {
                $tatCa[] = "{$h}:{$m}";
            }
        }

        return [
            'super_admin' => $tatCa,
            'admin' => $tatCa,
            'sale' => [...self::DON, ...self::XEM_COMBO],
            'marketing' => [...self::DON, ...self::SUA_COMBO],
            'dieu_hanh' => [...self::DON, ...self::XEM_COMBO],
            'ke_toan' => ['ViewAny:MaldivesBooking', 'View:MaldivesBooking'],
        ];
    }

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::theoVaiTro() as $vaiTro => $quyen) {
            // Vai trò bộ phận chưa có (máy mới cài) thì bỏ qua — VaiTroNhanVienSeeder tạo sau rồi gọi lại
            Role::where('name', $vaiTro)->where('guard_name', 'web')->first()
                ?->givePermissionTo(collect($quyen)->map(fn ($q) => Permission::findOrCreate($q, 'web')));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
