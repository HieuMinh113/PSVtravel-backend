<?php

declare(strict_types=1);

namespace Modules\Visa\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Visa\Models\VisaProvider;
use Illuminate\Auth\Access\HandlesAuthorization;

class VisaProviderPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:VisaProvider');
    }

    public function view(AuthUser $authUser, VisaProvider $visaProvider): bool
    {
        return $authUser->can('View:VisaProvider');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:VisaProvider');
    }

    public function update(AuthUser $authUser, VisaProvider $visaProvider): bool
    {
        return $authUser->can('Update:VisaProvider');
    }

    public function delete(AuthUser $authUser, VisaProvider $visaProvider): bool
    {
        return $authUser->can('Delete:VisaProvider');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:VisaProvider');
    }

    public function restore(AuthUser $authUser, VisaProvider $visaProvider): bool
    {
        return $authUser->can('Restore:VisaProvider');
    }

    public function forceDelete(AuthUser $authUser, VisaProvider $visaProvider): bool
    {
        return $authUser->can('ForceDelete:VisaProvider');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:VisaProvider');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:VisaProvider');
    }

    public function replicate(AuthUser $authUser, VisaProvider $visaProvider): bool
    {
        return $authUser->can('Replicate:VisaProvider');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:VisaProvider');
    }

}