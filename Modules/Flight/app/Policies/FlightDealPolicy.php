<?php

declare(strict_types=1);

namespace Modules\Flight\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Flight\Models\FlightDeal;
use Illuminate\Auth\Access\HandlesAuthorization;

class FlightDealPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:FlightDeal');
    }

    public function view(AuthUser $authUser, FlightDeal $flightDeal): bool
    {
        return $authUser->can('View:FlightDeal');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:FlightDeal');
    }

    public function update(AuthUser $authUser, FlightDeal $flightDeal): bool
    {
        return $authUser->can('Update:FlightDeal');
    }

    public function delete(AuthUser $authUser, FlightDeal $flightDeal): bool
    {
        return $authUser->can('Delete:FlightDeal');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:FlightDeal');
    }

    public function restore(AuthUser $authUser, FlightDeal $flightDeal): bool
    {
        return $authUser->can('Restore:FlightDeal');
    }

    public function forceDelete(AuthUser $authUser, FlightDeal $flightDeal): bool
    {
        return $authUser->can('ForceDelete:FlightDeal');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:FlightDeal');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:FlightDeal');
    }

    public function replicate(AuthUser $authUser, FlightDeal $flightDeal): bool
    {
        return $authUser->can('Replicate:FlightDeal');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:FlightDeal');
    }

}