<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Voucher;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class VoucherPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Voucher');
    }

    public function view(AuthUser $authUser, Voucher $record): bool
    {
        return $authUser->can('View:Voucher');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Voucher');
    }

    public function update(AuthUser $authUser, Voucher $record): bool
    {
        return $authUser->can('Update:Voucher');
    }

    public function delete(AuthUser $authUser, Voucher $record): bool
    {
        return $authUser->can('Delete:Voucher');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Voucher');
    }

    public function restore(AuthUser $authUser, Voucher $record): bool
    {
        return $authUser->can('Restore:Voucher');
    }

    public function forceDelete(AuthUser $authUser, Voucher $record): bool
    {
        return $authUser->can('ForceDelete:Voucher');
    }
}
