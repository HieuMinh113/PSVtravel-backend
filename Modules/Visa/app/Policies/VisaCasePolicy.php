<?php

declare(strict_types=1);

namespace Modules\Visa\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Visa\Models\VisaCase;

/** Quyền theo tên quyền (không theo tên vai trò) — giống TourPolicy. */
class VisaCasePolicy
{
    public function viewAny(AuthUser $u): bool
    {
        return $u->can('ViewAny:VisaCase');
    }

    public function view(AuthUser $u, VisaCase $banGhi): bool
    {
        return $u->can('View:VisaCase');
    }

    public function create(AuthUser $u): bool
    {
        return $u->can('Create:VisaCase');
    }

    public function update(AuthUser $u, VisaCase $banGhi): bool
    {
        return $u->can('Update:VisaCase');
    }

    public function delete(AuthUser $u, VisaCase $banGhi): bool
    {
        return $u->can('Delete:VisaCase');
    }

    public function deleteAny(AuthUser $u): bool
    {
        return $u->can('DeleteAny:VisaCase');
    }
}
