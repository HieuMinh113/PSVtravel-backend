<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Trả lại quyền đặc biệt mặc định cho vai trò có sẵn.
 *
 * Trước đây các quyền này không có ô trên màn hình Vai trò, nên ai mở vai trò
 * rồi bấm Lưu là chúng bị xoá (Kế toán mất quyền duyệt khoản thu...). Không thể
 * cố ý bỏ chúng qua màn hình, nên cấp lại đúng như mặc định là an toàn. Từ nay
 * chúng có ô ở thẻ quyền tuỳ chỉnh, muốn bỏ thì bỏ tích.
 */
return new class extends Migration
{
    private const MAC_DINH = [
        'Approve:Payment' => ['ke_toan', 'super_admin', 'admin'],
        'ViewAll:Booking' => ['ke_toan', 'super_admin', 'admin'],
        'ViewAll:Attendance' => ['ke_toan', 'super_admin', 'admin'],
        'ViewAll:VisaCase' => ['super_admin', 'admin'],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::MAC_DINH as $quyen => $vaiTro) {
            $p = Permission::findOrCreate($quyen, 'web');
            foreach (Role::whereIn('name', $vaiTro)->where('guard_name', 'web')->get() as $r) {
                $r->givePermissionTo($p);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Không thu hồi: đây là quyền mặc định vốn có của các vai trò này
    }
};
