<?php

declare(strict_types=1);

namespace Modules\Maldives\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Maldives\Models\MaldivesResort;

class MaldivesResortPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MaldivesResort');
    }

    public function view(AuthUser $authUser, MaldivesResort $maldivesResort): bool
    {
        return $authUser->can('View:MaldivesResort');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MaldivesResort');
    }

    public function update(AuthUser $authUser, MaldivesResort $maldivesResort): bool
    {
        return $authUser->can('Update:MaldivesResort');
    }

    public function delete(AuthUser $authUser, MaldivesResort $maldivesResort): bool
    {
        return $authUser->can('Delete:MaldivesResort');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:MaldivesResort');
    }

    public function restore(AuthUser $authUser, MaldivesResort $maldivesResort): bool
    {
        return $authUser->can('Restore:MaldivesResort');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MaldivesResort');
    }

    public function forceDelete(AuthUser $authUser, MaldivesResort $maldivesResort): bool
    {
        return $authUser->can('ForceDelete:MaldivesResort');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MaldivesResort');
    }

    public function replicate(AuthUser $authUser, MaldivesResort $maldivesResort): bool
    {
        return $authUser->can('Replicate:MaldivesResort');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MaldivesResort');
    }
}
