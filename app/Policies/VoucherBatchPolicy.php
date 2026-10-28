<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\VoucherBatch;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class VoucherBatchPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:VoucherBatch');
    }

    public function view(AuthUser $authUser, VoucherBatch $record): bool
    {
        return $authUser->can('View:VoucherBatch');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:VoucherBatch');
    }

    public function update(AuthUser $authUser, VoucherBatch $record): bool
    {
        return $authUser->can('Update:VoucherBatch');
    }

    public function delete(AuthUser $authUser, VoucherBatch $record): bool
    {
        return $authUser->can('Delete:VoucherBatch');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:VoucherBatch');
    }

    public function restore(AuthUser $authUser, VoucherBatch $record): bool
    {
        return $authUser->can('Restore:VoucherBatch');
    }

    public function forceDelete(AuthUser $authUser, VoucherBatch $record): bool
    {
        return $authUser->can('ForceDelete:VoucherBatch');
    }
}
