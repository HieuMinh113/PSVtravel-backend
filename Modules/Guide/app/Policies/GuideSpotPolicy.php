<?php

declare(strict_types=1);

namespace Modules\Guide\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Guide\Models\GuideSpot;

class GuideSpotPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:GuideSpot');
    }

    public function view(AuthUser $authUser, GuideSpot $guideSpot): bool
    {
        return $authUser->can('View:GuideSpot');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:GuideSpot');
    }

    public function update(AuthUser $authUser, GuideSpot $guideSpot): bool
    {
        return $authUser->can('Update:GuideSpot');
    }

    public function delete(AuthUser $authUser, GuideSpot $guideSpot): bool
    {
        return $authUser->can('Delete:GuideSpot');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:GuideSpot');
    }

    public function restore(AuthUser $authUser, GuideSpot $guideSpot): bool
    {
        return $authUser->can('Restore:GuideSpot');
    }

    public function forceDelete(AuthUser $authUser, GuideSpot $guideSpot): bool
    {
        return $authUser->can('ForceDelete:GuideSpot');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:GuideSpot');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:GuideSpot');
    }

    public function replicate(AuthUser $authUser, GuideSpot $guideSpot): bool
    {
        return $authUser->can('Replicate:GuideSpot');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:GuideSpot');
    }
}
