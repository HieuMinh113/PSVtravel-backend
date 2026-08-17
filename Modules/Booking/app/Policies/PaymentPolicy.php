<?php

namespace Modules\Booking\Policies;

use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:Booking');
    }

    public function view(User $user): bool
    {
        return $user->can('View:Booking');
    }

    public function create(User $user): bool
    {
        return $user->can('Update:Booking');
    }

    public function update(User $user): bool
    {
        return $user->can('Update:Booking');
    }

    // Xoá khoản thu làm sai lệch sổ sách — chỉ quản trị mới được
    public function delete(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin']);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}