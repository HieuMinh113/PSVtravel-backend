<?php

declare(strict_types=1);

namespace Modules\Moment\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Moment\Models\Moment;
use Illuminate\Auth\Access\HandlesAuthorization;

class MomentPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Moment');
    }

    public function view(AuthUser $authUser, Moment $moment): bool
    {
        return $authUser->can('View:Moment');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Moment');
    }

    public function update(AuthUser $authUser, Moment $moment): bool
    {
        return $authUser->can('Update:Moment');
    }

    public function delete(AuthUser $authUser, Moment $moment): bool
    {
        return $authUser->can('Delete:Moment');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Moment');
    }

    public function restore(AuthUser $authUser, Moment $moment): bool
    {
        return $authUser->can('Restore:Moment');
    }

    public function forceDelete(AuthUser $authUser, Moment $moment): bool
    {
        return $authUser->can('ForceDelete:Moment');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Moment');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Moment');
    }

    public function replicate(AuthUser $authUser, Moment $moment): bool
    {
        return $authUser->can('Replicate:Moment');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Moment');
    }

}