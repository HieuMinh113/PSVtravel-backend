<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Gỡ lượt chấm công bấm nhầm (VD vừa chấm vào đã bấm chấm ra):
 *  - cột removals: lịch sử gỡ (giờ đã gỡ, lý do, ai gỡ, lúc nào) — nhân viên thấy;
 *  - quyền "Remove:Attendance", mặc định cho super_admin và admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->json('removals')->nullable();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $quyen = Permission::findOrCreate('Remove:Attendance', 'web');
        foreach (Role::whereIn('name', ['super_admin', 'admin'])->where('guard_name', 'web')->get() as $r) {
            $r->givePermissionTo($quyen);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('removals');
        });
        Permission::where('name', 'Remove:Attendance')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
