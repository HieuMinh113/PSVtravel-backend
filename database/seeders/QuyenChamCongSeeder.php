<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Chấm công: mọi nhân viên vào được trang quản trị đều tự chấm công của mình
 * (không cần quyền riêng). Quyền "ViewAll:Attendance" = quản lý chấm công:
 * xem bảng chấm công của mọi người, duyệt lý do / bổ sung công, cho đăng ký
 * lại khuôn mặt, sửa cấu hình. Quyền "Remove:Attendance" = gỡ lượt chấm bấm nhầm.
 * Cả hai cấp cho super_admin và admin.
 *
 * Gọi từ migration VÀ từ RoleSeeder (máy mới cài). Chạy lại được.
 */
class QuyenChamCongSeeder extends Seeder
{
    public const QUYEN_QUAN_LY = 'ViewAll:Attendance';

    public const QUYEN_GO = 'Remove:Attendance';

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ([self::QUYEN_QUAN_LY, self::QUYEN_GO] as $ten) {
            $quyen = Permission::findOrCreate($ten, 'web');
            foreach (['super_admin', 'admin'] as $vaiTro) {
                Role::where('name', $vaiTro)->where('guard_name', 'web')->first()?->givePermissionTo($quyen);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
