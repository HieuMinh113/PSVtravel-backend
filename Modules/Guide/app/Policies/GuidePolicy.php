<?php

declare(strict_types=1);

namespace Modules\Guide\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Guide\Models\Guide;
use Illuminate\Auth\Access\HandlesAuthorization;

class GuidePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Guide');
    }

    public function view(AuthUser $authUser, Guide $guide): bool
    {
        return $authUser->can('View:Guide');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Guide');
    }

    public function update(AuthUser $authUser, Guide $guide): bool
    {
        return $authUser->can('Update:Guide');
    }

    public function delete(AuthUser $authUser, Guide $guide): bool
    {
        return $authUser->can('Delete:Guide');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Guide');
    }

    public function restore(AuthUser $authUser, Guide $guide): bool
    {
        return $authUser->can('Restore:Guide');
    }

    public function forceDelete(AuthUser $authUser, Guide $guide): bool
    {
        return $authUser->can('ForceDelete:Guide');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Guide');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Guide');
    }

    public function replicate(AuthUser $authUser, Guide $guide): bool
    {
        return $authUser->can('Replicate:Guide');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Guide');
    }

}