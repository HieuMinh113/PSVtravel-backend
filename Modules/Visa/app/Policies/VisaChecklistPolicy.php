<?php

declare(strict_types=1);

namespace Modules\Visa\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Visa\Models\VisaChecklist;

/** Quyền theo tên quyền (không theo tên vai trò) — giống TourPolicy. */
class VisaChecklistPolicy
{
    public function viewAny(AuthUser $u): bool
    {
        return $u->can('ViewAny:VisaChecklist');
    }

    public function view(AuthUser $u, VisaChecklist $banGhi): bool
    {
        return $u->can('View:VisaChecklist');
    }

    public function create(AuthUser $u): bool
    {
        return $u->can('Create:VisaChecklist');
    }

    public function update(AuthUser $u, VisaChecklist $banGhi): bool
    {
        return $u->can('Update:VisaChecklist');
    }

    public function delete(AuthUser $u, VisaChecklist $banGhi): bool
    {
        return $u->can('Delete:VisaChecklist');
    }

    public function deleteAny(AuthUser $u): bool
    {
        return $u->can('DeleteAny:VisaChecklist');
    }
}
