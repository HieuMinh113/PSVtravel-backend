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
 * lại khuôn mặt, sửa cấu hình. Cấp cho super_admin và admin.
 *
 * Gọi từ migration VÀ từ RoleSeeder (máy mới cài). Chạy lại được.
 */
class QuyenChamCongSeeder extends Seeder
{
    public const QUYEN_QUAN_LY = 'ViewAll:Attendance';

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $quyen = Permission::findOrCreate(self::QUYEN_QUAN_LY, 'web');
        foreach (['super_admin', 'admin'] as $ten) {
            Role::where('name', $ten)->where('guard_name', 'web')->first()?->givePermissionTo($quyen);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
