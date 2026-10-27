<?php

declare(strict_types=1);

namespace Modules\Maldives\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Maldives\Models\MaldivesCombo;

class MaldivesComboPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MaldivesCombo');
    }

    public function view(AuthUser $authUser, MaldivesCombo $maldivesCombo): bool
    {
        return $authUser->can('View:MaldivesCombo');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MaldivesCombo');
    }

    public function update(AuthUser $authUser, MaldivesCombo $maldivesCombo): bool
    {
        return $authUser->can('Update:MaldivesCombo');
    }

    public function delete(AuthUser $authUser, MaldivesCombo $maldivesCombo): bool
    {
        return $authUser->can('Delete:MaldivesCombo');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:MaldivesCombo');
    }

    public function restore(AuthUser $authUser, MaldivesCombo $maldivesCombo): bool
    {
        return $authUser->can('Restore:MaldivesCombo');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MaldivesCombo');
    }

    public function forceDelete(AuthUser $authUser, MaldivesCombo $maldivesCombo): bool
    {
        return $authUser->can('ForceDelete:MaldivesCombo');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MaldivesCombo');
    }

    public function replicate(AuthUser $authUser, MaldivesCombo $maldivesCombo): bool
    {
        return $authUser->can('Replicate:MaldivesCombo');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MaldivesCombo');
    }
}
