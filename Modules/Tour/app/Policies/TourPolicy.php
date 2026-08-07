<?php

declare(strict_types=1);

namespace Modules\Tour\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Tour\Models\Tour;
use Illuminate\Auth\Access\HandlesAuthorization;

class TourPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Tour');
    }

    public function view(AuthUser $authUser, Tour $tour): bool
    {
        return $authUser->can('View:Tour');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Tour');
    }

    public function update(AuthUser $authUser, Tour $tour): bool
    {
        return $authUser->can('Update:Tour');
    }

    public function delete(AuthUser $authUser, Tour $tour): bool
    {
        return $authUser->can('Delete:Tour');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Tour');
    }

    public function restore(AuthUser $authUser, Tour $tour): bool
    {
        return $authUser->can('Restore:Tour');
    }

    public function forceDelete(AuthUser $authUser, Tour $tour): bool
    {
        return $authUser->can('ForceDelete:Tour');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Tour');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Tour');
    }

    public function replicate(AuthUser $authUser, Tour $tour): bool
    {
        return $authUser->can('Replicate:Tour');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Tour');
    }

}