<?php

declare(strict_types=1);

namespace Modules\Alliance\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Alliance\Models\AllianceSource;

/** Quyền theo tên quyền (không theo tên vai trò) — giống TourPolicy. */
class AllianceSourcePolicy
{
    public function viewAny(AuthUser $u): bool
    {
        return $u->can('ViewAny:AllianceSource');
    }

    public function view(AuthUser $u, AllianceSource $nguon): bool
    {
        return $u->can('View:AllianceSource');
    }

    public function create(AuthUser $u): bool
    {
        return $u->can('Create:AllianceSource');
    }

    public function update(AuthUser $u, AllianceSource $nguon): bool
    {
        return $u->can('Update:AllianceSource');
    }

    public function delete(AuthUser $u, AllianceSource $nguon): bool
    {
        return $u->can('Delete:AllianceSource');
    }

    public function deleteAny(AuthUser $u): bool
    {
        return $u->can('DeleteAny:AllianceSource');
    }
}
