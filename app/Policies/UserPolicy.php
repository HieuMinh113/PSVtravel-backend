<?php

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Tài khoản SUPER ADMIN chỉ super admin khác được đụng vào.
     *
     * Trước đây ai có quyền "Sửa người dùng" (vd. vai trò admin) đều mở được
     * tài khoản super admin để đổi mật khẩu / email / xoá — không chiếm được
     * (đã có 2FA) nhưng KHOÁ được chủ hệ thống ra ngoài. Quyền cấp dưới không
     * được sửa tài khoản cấp trên.
     */
    private function duocDungVao(AuthUser $nguoiLam, ?AuthUser $taiKhoan): bool
    {
        $sieuQuanTri = config('filament-shield.super_admin.name');

        if (! $taiKhoan || ! $sieuQuanTri || ! method_exists($taiKhoan, 'hasRole')
            || ! $taiKhoan->hasRole($sieuQuanTri)) {
            return true;
        }

        return method_exists($nguoiLam, 'hasRole') && $nguoiLam->hasRole($sieuQuanTri);
    }
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:User');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:User');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:User');
    }

    public function update(AuthUser $authUser, ?AuthUser $model = null): bool
    {
        return $authUser->can('Update:User') && $this->duocDungVao($authUser, $model);
    }

    public function delete(AuthUser $authUser, ?AuthUser $model = null): bool
    {
        return $authUser->can('Delete:User') && $this->duocDungVao($authUser, $model);
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:User');
    }

    public function restore(AuthUser $authUser, ?AuthUser $model = null): bool
    {
        return $authUser->can('Restore:User') && $this->duocDungVao($authUser, $model);
    }

    public function forceDelete(AuthUser $authUser, ?AuthUser $model = null): bool
    {
        return $authUser->can('ForceDelete:User') && $this->duocDungVao($authUser, $model);
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:User');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:User');
    }

    public function replicate(AuthUser $authUser, ?AuthUser $model = null): bool
    {
        return $authUser->can('Replicate:User') && $this->duocDungVao($authUser, $model);
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:User');
    }

}