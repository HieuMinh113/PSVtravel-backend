<?php

namespace Modules\Booking\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Quyền riêng của đơn tour + vai trò "ke_toan". Chạy lại bao nhiêu lần cũng được.
 *
 * Gọi từ migration (máy chủ thật có ngay khi triển khai) VÀ từ RoleSeeder
 * (máy mới cài: migration chạy trước khi RoleSeeder tạo super_admin/admin).
 */
class QuyenDonTourSeeder extends Seeder
{
    /**
     * ViewAll:Booking — đổi người phụ trách đơn, xem thống kê của MỌI nhân
     * viên (không có thì chỉ xem của mình).
     * Approve:Payment — kế toán duyệt khoản thu ("Đã nhận tiền").
     */
    public const QUYEN_QUAN_LY = ['ViewAll:Booking', 'Approve:Payment'];

    /** Kế toán: xem đơn + khoản thu, duyệt khoản thu, xem thống kê của mọi người. Không sửa đơn. */
    public const QUYEN_KE_TOAN = ['ViewAny:Booking', 'View:Booking', 'Approve:Payment', 'ViewAll:Booking'];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $tao = fn (array $ds) => collect($ds)->map(fn ($q) => Permission::findOrCreate($q, 'web'));

        Role::findOrCreate('ke_toan', 'web')->givePermissionTo($tao(self::QUYEN_KE_TOAN));
        foreach (['super_admin', 'admin'] as $ten) {
            Role::where('name', $ten)->where('guard_name', 'web')->first()?->givePermissionTo($tao(self::QUYEN_QUAN_LY));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
