<?php

declare(strict_types=1);

namespace Modules\Visa\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Visa\Models\VisaCountry;
use Illuminate\Auth\Access\HandlesAuthorization;

class VisaCountryPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:VisaCountry');
    }

    public function view(AuthUser $authUser, VisaCountry $visaCountry): bool
    {
        return $authUser->can('View:VisaCountry');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:VisaCountry');
    }

    public function update(AuthUser $authUser, VisaCountry $visaCountry): bool
    {
        return $authUser->can('Update:VisaCountry');
    }

    public function delete(AuthUser $authUser, VisaCountry $visaCountry): bool
    {
        return $authUser->can('Delete:VisaCountry');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:VisaCountry');
    }

    public function restore(AuthUser $authUser, VisaCountry $visaCountry): bool
    {
        return $authUser->can('Restore:VisaCountry');
    }

    public function forceDelete(AuthUser $authUser, VisaCountry $visaCountry): bool
    {
        return $authUser->can('ForceDelete:VisaCountry');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:VisaCountry');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:VisaCountry');
    }

    public function replicate(AuthUser $authUser, VisaCountry $visaCountry): bool
    {
        return $authUser->can('Replicate:VisaCountry');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:VisaCountry');
    }

}