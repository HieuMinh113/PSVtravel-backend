<?php

namespace App\Policies\Concerns;

use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Phân quyền chung cho các nội dung do admin/nhân viên quản lý:
 * - super_admin, admin, staff: xem, thêm, sửa.
 * - super_admin, admin: xoá.
 * - super_admin: xoá vĩnh viễn.
 *
 * Dùng vai trò thay vì quyền chi tiết vì các model mới chưa đăng ký quyền
 * trong hệ phân quyền (giống ContactMessage/AboutImage).
 */
trait PhanQuyenNoiDung
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
        return $u->hasAnyRole(['super_admin', 'admin', 'staff']);
    }

    public function update(AuthUser $u, $record): bool
    {
        return $u->hasAnyRole(['super_admin', 'admin', 'staff']);
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
