<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Vai trò "customer" bị mất trên máy chủ thật → mọi lượt đăng ký trên website
 * lỗi 500 ngay sau khi tạo tài khoản (gán vai trò thất bại, chưa gửi mã).
 *
 * Tạo lại vai trò, và gán cho các tài khoản đăng ký dở dang đó: chưa có vai
 * trò nào, chưa có quyền nào, chưa xác thực email. Tài khoản nhân viên do
 * admin tạo (đã xác thực / đã có vai trò) không bị đụng tới. Các khách này
 * đăng nhập lại là được gửi mã xác thực như bình thường.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $khach = Role::findOrCreate('customer', 'web');

        User::query()
            ->whereNull('email_verified_at')
            ->whereDoesntHave('roles')
            ->whereDoesntHave('permissions')
            ->each(fn (User $u) => $u->assignRole($khach));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Không gỡ vai trò khách hàng
    }
};
