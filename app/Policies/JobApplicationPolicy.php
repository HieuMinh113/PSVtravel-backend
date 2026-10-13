<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\JobApplication;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Hồ sơ ứng tuyển — CV là dữ liệu cá nhân nên dùng quyền chi tiết
 * (cấp sẵn cho super_admin + admin, xem QuyenHoSoUngTuyenSeeder).
 */
class JobApplicationPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:JobApplication');
    }

    public function view(AuthUser $authUser, JobApplication $jobApplication): bool
    {
        return $authUser->can('View:JobApplication');
    }

    // Hồ sơ chỉ đến từ form ngoài website
    public function create(AuthUser $authUser): bool
    {
        return false;
    }

    public function update(AuthUser $authUser, JobApplication $jobApplication): bool
    {
        return $authUser->can('Update:JobApplication');
    }

    public function delete(AuthUser $authUser, JobApplication $jobApplication): bool
    {
        return $authUser->can('Delete:JobApplication');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:JobApplication');
    }

    public function restore(AuthUser $authUser, JobApplication $jobApplication): bool
    {
        return false;
    }

    public function forceDelete(AuthUser $authUser, JobApplication $jobApplication): bool
    {
        return $authUser->can('ForceDelete:JobApplication');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:JobApplication');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function replicate(AuthUser $authUser, JobApplication $jobApplication): bool
    {
        return false;
    }

    public function reorder(AuthUser $authUser): bool
    {
        return false;
    }
}
