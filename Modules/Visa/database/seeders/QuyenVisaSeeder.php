<?php

namespace Modules\Visa\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Vai trò "visa" + quyền hồ sơ visa và mẫu checklist, cấp thêm cho
 * super_admin và admin. Chạy lại bao nhiêu lần cũng được.
 *
 * Gọi từ migration (máy chủ thật có ngay khi triển khai) VÀ từ RoleSeeder
 * (máy mới cài: migration chạy trước khi RoleSeeder tạo super_admin/admin).
 */
class QuyenVisaSeeder extends Seeder
{
    public const QUYEN = [
        'ViewAny:VisaCase', 'View:VisaCase', 'Create:VisaCase', 'Update:VisaCase', 'Delete:VisaCase', 'DeleteAny:VisaCase',
        'ViewAny:VisaChecklist', 'View:VisaChecklist', 'Create:VisaChecklist', 'Update:VisaChecklist', 'Delete:VisaChecklist', 'DeleteAny:VisaChecklist',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $quyen = collect(self::QUYEN)->map(fn ($q) => Permission::findOrCreate($q, 'web'));

        Role::findOrCreate('visa', 'web')->givePermissionTo($quyen);
        foreach (['super_admin', 'admin'] as $ten) {
            Role::where('name', $ten)->where('guard_name', 'web')->first()?->givePermissionTo($quyen);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
