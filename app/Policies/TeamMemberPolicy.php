<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TeamMember;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Đội ngũ. Role-based như EventPolicy để super_admin/admin/staff thấy và quản
 * lý được ngay, không cần seed quyền chi tiết.
 */
class TeamMemberPolicy
{
    use HandlesAuthorization;

    private const QUAN_TRI = ['super_admin', 'admin', 'staff'];
    private const CAP_CAO = ['super_admin', 'admin'];

    private function co(AuthUser $u, array $vaiTro, string $quyen): bool
    {
        return $u->hasAnyRole($vaiTro) || $u->can($quyen);
    }

    public function viewAny(AuthUser $u): bool
    {
        return $this->co($u, self::QUAN_TRI, 'ViewAny:TeamMember');
    }

    public function view(AuthUser $u, TeamMember $m): bool
    {
        return $this->co($u, self::QUAN_TRI, 'View:TeamMember');
    }

    public function create(AuthUser $u): bool
    {
        return $this->co($u, self::CAP_CAO, 'Create:TeamMember');
    }

    public function update(AuthUser $u, TeamMember $m): bool
    {
        return $this->co($u, self::CAP_CAO, 'Update:TeamMember');
    }

    public function delete(AuthUser $u, TeamMember $m): bool
    {
        return $this->co($u, self::CAP_CAO, 'Delete:TeamMember');
    }

    public function deleteAny(AuthUser $u): bool
    {
        return $this->co($u, self::CAP_CAO, 'DeleteAny:TeamMember');
    }

    public function restore(AuthUser $u, TeamMember $m): bool
    {
        return $this->co($u, self::CAP_CAO, 'Restore:TeamMember');
    }

    public function forceDelete(AuthUser $u, TeamMember $m): bool
    {
        return $u->hasRole('super_admin') || $u->can('ForceDelete:TeamMember');
    }
}
