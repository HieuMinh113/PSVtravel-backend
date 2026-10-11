<?php

declare(strict_types=1);

namespace Modules\Guide\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Guide\Models\GuideCity;

class GuideCityPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:GuideCity');
    }

    public function view(AuthUser $authUser, GuideCity $guideCity): bool
    {
        return $authUser->can('View:GuideCity');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:GuideCity');
    }

    public function update(AuthUser $authUser, GuideCity $guideCity): bool
    {
        return $authUser->can('Update:GuideCity');
    }

    public function delete(AuthUser $authUser, GuideCity $guideCity): bool
    {
        return $authUser->can('Delete:GuideCity');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:GuideCity');
    }

    public function restore(AuthUser $authUser, GuideCity $guideCity): bool
    {
        return $authUser->can('Restore:GuideCity');
    }

    public function forceDelete(AuthUser $authUser, GuideCity $guideCity): bool
    {
        return $authUser->can('ForceDelete:GuideCity');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:GuideCity');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:GuideCity');
    }

    public function replicate(AuthUser $authUser, GuideCity $guideCity): bool
    {
        return $authUser->can('Replicate:GuideCity');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:GuideCity');
    }
}
