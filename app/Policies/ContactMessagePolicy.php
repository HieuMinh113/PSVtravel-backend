<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ContactMessage;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Tin liên hệ do khách gửi từ website.
 *
 * Khác các policy khác trong dự án (chỉ dựa vào quyền chi tiết dạng
 * "ViewAny:Moment"), ở đây chấp nhận cả vai trò quản trị. Lý do: quyền chi tiết
 * cho model mới này chưa được tạo trong hệ thống phân quyền, nếu chỉ dựa vào
 * quyền thì không ai — kể cả super_admin — nhìn thấy tin khách gửi.
 */
class ContactMessagePolicy
{
    use HandlesAuthorization;

    private const VAI_TRO_XU_LY = ['super_admin', 'admin', 'staff'];

    public function viewAny(AuthUser $authUser): bool
    {
        return $this->duocXuLy($authUser, 'ViewAny:ContactMessage');
    }

    public function view(AuthUser $authUser, ContactMessage $contactMessage): bool
    {
        return $this->duocXuLy($authUser, 'View:ContactMessage');
    }

    // Không ai tạo tay: tin chỉ đến từ form ngoài website
    public function create(AuthUser $authUser): bool
    {
        return false;
    }

    public function update(AuthUser $authUser, ContactMessage $contactMessage): bool
    {
        return $this->duocXuLy($authUser, 'Update:ContactMessage');
    }

    // Xoá tin khách là mất dấu vết liên hệ — chỉ quản trị cấp cao được làm
    public function delete(AuthUser $authUser, ContactMessage $contactMessage): bool
    {
        return $authUser->hasAnyRole(['super_admin', 'admin'])
            || $authUser->can('Delete:ContactMessage');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->hasAnyRole(['super_admin', 'admin'])
            || $authUser->can('DeleteAny:ContactMessage');
    }

    public function restore(AuthUser $authUser, ContactMessage $contactMessage): bool
    {
        return $this->delete($authUser, $contactMessage);
    }

    public function forceDelete(AuthUser $authUser, ContactMessage $contactMessage): bool
    {
        return $authUser->hasRole('super_admin');
    }

    private function duocXuLy(AuthUser $authUser, string $quyen): bool
    {
        return $authUser->hasAnyRole(self::VAI_TRO_XU_LY) || $authUser->can($quyen);
    }
}
