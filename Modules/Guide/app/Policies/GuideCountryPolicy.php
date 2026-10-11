<?php

declare(strict_types=1);

namespace Modules\Guide\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Guide\Models\GuideCountry;

class GuideCountryPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:GuideCountry');
    }

    public function view(AuthUser $authUser, GuideCountry $guideCountry): bool
    {
        return $authUser->can('View:GuideCountry');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:GuideCountry');
    }

    public function update(AuthUser $authUser, GuideCountry $guideCountry): bool
    {
        return $authUser->can('Update:GuideCountry');
    }

    public function delete(AuthUser $authUser, GuideCountry $guideCountry): bool
    {
        return $authUser->can('Delete:GuideCountry');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:GuideCountry');
    }

    public function restore(AuthUser $authUser, GuideCountry $guideCountry): bool
    {
        return $authUser->can('Restore:GuideCountry');
    }

    public function forceDelete(AuthUser $authUser, GuideCountry $guideCountry): bool
    {
        return $authUser->can('ForceDelete:GuideCountry');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:GuideCountry');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:GuideCountry');
    }

    public function replicate(AuthUser $authUser, GuideCountry $guideCountry): bool
    {
        return $authUser->can('Replicate:GuideCountry');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:GuideCountry');
    }
}
