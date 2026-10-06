<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Email khách đăng ký nhận ưu đãi. Không ai tạo tay trong admin (chỉ đến từ
 * form ngoài web). Nhân viên xem được; chỉ admin xoá.
 */
class SubscriberPolicy
{
    public function viewAny(AuthUser $u): bool
    {
        return $u->hasAnyRole(['super_admin', 'admin', 'staff']);
    }

    public function view(AuthUser $u, $record): bool
    {
        return $u->hasAnyRole(['super_admin', 'admin', 'staff']);
    }

    public function create(AuthUser $u): bool
    {
        return false;
    }

    public function update(AuthUser $u, $record): bool
    {
        return $u->hasAnyRole(['super_admin', 'admin']);
    }

    public function delete(AuthUser $u, $record): bool
    {
        return $u->hasAnyRole(['super_admin', 'admin']);
    }

    public function deleteAny(AuthUser $u): bool
    {
        return $u->hasAnyRole(['super_admin', 'admin']);
    }

    public function restore(AuthUser $u, $record): bool
    {
        return $u->hasAnyRole(['super_admin', 'admin']);
    }

    public function forceDelete(AuthUser $u, $record): bool
    {
        return $u->hasRole('super_admin');
    }
}
