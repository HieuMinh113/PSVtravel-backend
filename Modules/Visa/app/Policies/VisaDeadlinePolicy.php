<?php

declare(strict_types=1);

namespace Modules\Visa\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Visa\Models\VisaDeadline;

/** Quyền theo tên quyền (không theo tên vai trò) — giống TourPolicy. */
class VisaDeadlinePolicy
{
    public function viewAny(AuthUser $u): bool
    {
        return $u->can('ViewAny:VisaDeadline');
    }

    public function view(AuthUser $u, VisaDeadline $banGhi): bool
    {
        return $u->can('View:VisaDeadline');
    }

    public function create(AuthUser $u): bool
    {
        return $u->can('Create:VisaDeadline');
    }

    public function update(AuthUser $u, VisaDeadline $banGhi): bool
    {
        return $u->can('Update:VisaDeadline');
    }

    public function delete(AuthUser $u, VisaDeadline $banGhi): bool
    {
        return $u->can('Delete:VisaDeadline');
    }

    public function deleteAny(AuthUser $u): bool
    {
        return $u->can('DeleteAny:VisaDeadline');
    }
}
