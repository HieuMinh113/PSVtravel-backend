<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Quyền xem / xử lý hồ sơ ứng tuyển (CV là dữ liệu cá nhân — chỉ cấp sẵn cho
 * super_admin và admin; muốn cho thêm ai thì cấp tay trong Vai trò).
 * Chạy lại bao nhiêu lần cũng được.
 *
 * Gọi từ migration (máy đang chạy) VÀ từ RoleSeeder (máy mới cài: migration
 * chạy trước khi có vai trò super_admin).
 */
class QuyenHoSoUngTuyenSeeder extends Seeder
{
    private const THAO_TAC = [
        'ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny', 'Restore',
        'ForceDelete', 'ForceDeleteAny', 'RestoreAny', 'Replicate', 'Reorder',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $quyen = collect(self::THAO_TAC)->map(fn ($t) => Permission::findOrCreate("{$t}:JobApplication", 'web'));

        foreach (Role::query()->where('guard_name', 'web')->whereIn('name', ['super_admin', 'admin'])->get() as $r) {
            $r->givePermissionTo($quyen);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
