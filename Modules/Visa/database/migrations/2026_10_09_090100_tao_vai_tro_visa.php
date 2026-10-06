<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Visa\Database\Seeders\MauChecklistVisaSeeder;
use Modules\Visa\Database\Seeders\QuyenVisaSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Vai trò "visa" + quyền hồ sơ visa (xem QuyenVisaSeeder) và vài mẫu checklist
 * khởi đầu. Muốn cấp cho nhân viên nào thì vào Người dùng → chọn vai trò "visa".
 */
return new class extends Migration
{
    public function up(): void
    {
        (new QuyenVisaSeeder)->run();
        (new MauChecklistVisaSeeder)->run();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::where('name', 'visa')->where('guard_name', 'web')->delete();
        Permission::whereIn('name', QuyenVisaSeeder::QUYEN)->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
