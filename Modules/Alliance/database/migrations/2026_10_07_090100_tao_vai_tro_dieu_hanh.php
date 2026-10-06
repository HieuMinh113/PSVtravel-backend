<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Alliance\Database\Seeders\QuyenLienMinhSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Vai trò "dieu_hanh" (điều hành) + quyền liên minh — xem QuyenLienMinhSeeder.
 * Muốn cấp cho nhân viên nào thì vào Người dùng → chọn vai trò "dieu_hanh".
 */
return new class extends Migration
{
    public function up(): void
    {
        (new QuyenLienMinhSeeder)->run();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::where('name', 'dieu_hanh')->where('guard_name', 'web')->delete();
        Permission::whereIn('name', QuyenLienMinhSeeder::QUYEN)->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
