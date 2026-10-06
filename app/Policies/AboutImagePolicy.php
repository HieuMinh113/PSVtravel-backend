<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AboutImage;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Ảnh trang "Về chúng tôi". Dựa vào vai trò quản trị (giống ContactMessage):
 * quyền chi tiết cho model mới chưa tạo trong hệ phân quyền nên phải chấp nhận
 * vai trò, nếu không sẽ không ai thấy được mục này.
 */
class AboutImagePolicy
{
    use HandlesAuthorization;

    private const VAI_TRO_QUAN_TRI = ['super_admin', 'admin', 'staff'];

    private const VAI_TRO_CAP_CAO = ['super_admin', 'admin'];

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->hasAnyRole(self::VAI_TRO_QUAN_TRI);
    }

    public function view(AuthUser $authUser, AboutImage $aboutImage): bool
    {
        return $authUser->hasAnyRole(self::VAI_TRO_QUAN_TRI);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->hasAnyRole(self::VAI_TRO_QUAN_TRI);
    }

    public function update(AuthUser $authUser, AboutImage $aboutImage): bool
    {
        return $authUser->hasAnyRole(self::VAI_TRO_QUAN_TRI);
    }

    public function delete(AuthUser $authUser, AboutImage $aboutImage): bool
    {
        return $authUser->hasAnyRole(self::VAI_TRO_CAP_CAO);
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->hasAnyRole(self::VAI_TRO_CAP_CAO);
    }

    public function restore(AuthUser $authUser, AboutImage $aboutImage): bool
    {
        return $authUser->hasAnyRole(self::VAI_TRO_CAP_CAO);
    }

    public function forceDelete(AuthUser $authUser, AboutImage $aboutImage): bool
    {
        return $authUser->hasRole('super_admin');
    }
}
