<?php

namespace Modules\Tour\Policies;

use App\Models\User;

class TourItineraryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:Tour');
    }

    public function view(User $user): bool
    {
        return $user->can('View:Tour');
    }

    public function create(User $user): bool
    {
        return $user->can('Update:Tour');
    }

    public function update(User $user): bool
    {
        return $user->can('Update:Tour');
    }

    public function delete(User $user): bool
    {
        return $user->can('Update:Tour');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('Update:Tour');
    }
}