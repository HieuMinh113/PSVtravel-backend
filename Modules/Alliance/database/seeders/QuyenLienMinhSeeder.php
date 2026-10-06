<?php

namespace Modules\Alliance\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Vai trò "dieu_hanh" (điều hành) + quyền xem / quản lý liên minh, cấp thêm
 * cho super_admin và admin. Chạy lại bao nhiêu lần cũng được.
 *
 * Gọi từ migration (máy chủ thật có ngay khi triển khai) VÀ từ DatabaseSeeder
 * (máy mới cài: migration chạy trước khi RoleSeeder tạo super_admin/admin).
 */
class QuyenLienMinhSeeder extends Seeder
{
    public const QUYEN = [
        'ViewAny:AllianceSource', 'View:AllianceSource', 'Create:AllianceSource',
        'Update:AllianceSource', 'Delete:AllianceSource', 'DeleteAny:AllianceSource',
        'ViewAny:AllianceDeparture', 'View:AllianceDeparture',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $quyen = collect(self::QUYEN)->map(fn ($q) => Permission::findOrCreate($q, 'web'));

        Role::findOrCreate('dieu_hanh', 'web')->givePermissionTo($quyen);
        foreach (['super_admin', 'admin'] as $ten) {
            Role::where('name', $ten)->where('guard_name', 'web')->first()?->givePermissionTo($quyen);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
