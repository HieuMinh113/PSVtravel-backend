<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EventReview;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Đánh giá gói sự kiện / team building. Role-based như EventPolicy /
 * ContactMessagePolicy để super_admin/admin/staff thấy và duyệt được ngay,
 * không cần seed quyền chi tiết.
 */
class EventReviewPolicy
{
    use HandlesAuthorization;

    private const QUAN_TRI = ['super_admin', 'admin', 'staff'];
    private const CAP_CAO = ['super_admin', 'admin'];

    private function co(AuthUser $u, array $vaiTro, string $quyen): bool
    {
        return $u->hasAnyRole($vaiTro) || $u->can($quyen);
    }

    public function viewAny(AuthUser $u): bool
    {
        return $this->co($u, self::QUAN_TRI, 'ViewAny:EventReview');
    }

    public function view(AuthUser $u, EventReview $r): bool
    {
        return $this->co($u, self::QUAN_TRI, 'View:EventReview');
    }

    // Đánh giá đến từ form ngoài web, admin không tạo tay
    public function create(AuthUser $u): bool
    {
        return false;
    }

    public function update(AuthUser $u, EventReview $r): bool
    {
        return $this->co($u, self::QUAN_TRI, 'Update:EventReview');
    }

    public function delete(AuthUser $u, EventReview $r): bool
    {
        return $this->co($u, self::CAP_CAO, 'Delete:EventReview');
    }

    public function deleteAny(AuthUser $u): bool
    {
        return $this->co($u, self::CAP_CAO, 'DeleteAny:EventReview');
    }

    public function restore(AuthUser $u, EventReview $r): bool
    {
        return $this->co($u, self::CAP_CAO, 'Restore:EventReview');
    }

    public function forceDelete(AuthUser $u, EventReview $r): bool
    {
        return $u->hasRole('super_admin') || $u->can('ForceDelete:EventReview');
    }
}
