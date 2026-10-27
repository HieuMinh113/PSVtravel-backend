<?php

declare(strict_types=1);

namespace Modules\Maldives\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Maldives\Models\MaldivesBooking;

class MaldivesBookingPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MaldivesBooking');
    }

    public function view(AuthUser $authUser, MaldivesBooking $maldivesBooking): bool
    {
        return $authUser->can('View:MaldivesBooking');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MaldivesBooking');
    }

    public function update(AuthUser $authUser, MaldivesBooking $maldivesBooking): bool
    {
        return $authUser->can('Update:MaldivesBooking');
    }

    public function delete(AuthUser $authUser, MaldivesBooking $maldivesBooking): bool
    {
        return $authUser->can('Delete:MaldivesBooking');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:MaldivesBooking');
    }

    public function restore(AuthUser $authUser, MaldivesBooking $maldivesBooking): bool
    {
        return $authUser->can('Restore:MaldivesBooking');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MaldivesBooking');
    }

    public function forceDelete(AuthUser $authUser, MaldivesBooking $maldivesBooking): bool
    {
        return $authUser->can('ForceDelete:MaldivesBooking');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MaldivesBooking');
    }

    public function replicate(AuthUser $authUser, MaldivesBooking $maldivesBooking): bool
    {
        return $authUser->can('Replicate:MaldivesBooking');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MaldivesBooking');
    }
}
