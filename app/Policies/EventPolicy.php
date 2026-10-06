<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Event;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Gói sự kiện / team building.
 *
 * Giống ContactMessagePolicy: quyền chi tiết dạng "ViewAny:Event" chưa được tạo
 * trong hệ thống phân quyền, nên ở đây chấp nhận cả vai trò quản trị nội dung
 * (super_admin, admin, staff) — nếu chỉ dựa vào quyền chi tiết thì không ai,
 * kể cả super_admin, nhìn thấy mục này. Khi nào cần siết theo quyền chi tiết
 * thì thêm permission "…:Event" và cách này vẫn tương thích.
 */
class EventPolicy
{
    use HandlesAuthorization;

    private const VAI_TRO_QUAN_TRI = ['super_admin', 'admin', 'staff'];
    private const VAI_TRO_CAP_CAO = ['super_admin', 'admin'];

    private function coVaiTro(AuthUser $u, array $vaiTro, string $quyen): bool
    {
        return $u->hasAnyRole($vaiTro) || $u->can($quyen);
    }

    public function viewAny(AuthUser $u): bool
    {
        return $this->coVaiTro($u, self::VAI_TRO_QUAN_TRI, 'ViewAny:Event');
    }

    public function view(AuthUser $u, Event $event): bool
    {
        return $this->coVaiTro($u, self::VAI_TRO_QUAN_TRI, 'View:Event');
    }

    public function create(AuthUser $u): bool
    {
        return $this->coVaiTro($u, self::VAI_TRO_CAP_CAO, 'Create:Event');
    }

    public function update(AuthUser $u, Event $event): bool
    {
        return $this->coVaiTro($u, self::VAI_TRO_CAP_CAO, 'Update:Event');
    }

    public function delete(AuthUser $u, Event $event): bool
    {
        return $this->coVaiTro($u, self::VAI_TRO_CAP_CAO, 'Delete:Event');
    }

    public function deleteAny(AuthUser $u): bool
    {
        return $this->coVaiTro($u, self::VAI_TRO_CAP_CAO, 'DeleteAny:Event');
    }

    public function restore(AuthUser $u, Event $event): bool
    {
        return $this->coVaiTro($u, self::VAI_TRO_CAP_CAO, 'Restore:Event');
    }

    public function forceDelete(AuthUser $u, Event $event): bool
    {
        return $u->hasRole('super_admin') || $u->can('ForceDelete:Event');
    }
}
