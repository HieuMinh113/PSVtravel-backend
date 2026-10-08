<?php

declare(strict_types=1);

namespace Modules\Booking\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Booking\Models\Booking;

class BookingPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Booking');
    }

    public function view(AuthUser $authUser, Booking $booking): bool
    {
        return $authUser->can('View:Booking');
    }

    /** Đổi người phụ trách (hưởng hoa hồng) và xem thống kê của mọi nhân viên. */
    public function giao(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAll:Booking');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Booking');
    }

    /**
     * Sửa / xác nhận / hoàn thành / huỷ đơn / thêm khoản thu. Nhân viên chỉ được
     * với đơn của mình (người phụ trách) và đơn khách đặt web chưa ai nhận;
     * quản lý (ViewAll:Booking) được mọi đơn.
     */
    public function update(AuthUser $authUser, Booking $booking): bool
    {
        return $authUser->can('Update:Booking') && self::cuaMinh($authUser, $booking);
    }

    public static function cuaMinh(AuthUser $authUser, Booking $booking): bool
    {
        return $authUser->can('ViewAll:Booking')
            || $booking->assigned_to === null
            || (int) $booking->assigned_to === (int) $authUser->getKey();
    }

    public function delete(AuthUser $authUser, Booking $booking): bool
    {
        return $authUser->can('Delete:Booking');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Booking');
    }

    public function restore(AuthUser $authUser, Booking $booking): bool
    {
        return $authUser->can('Restore:Booking');
    }

    public function forceDelete(AuthUser $authUser, Booking $booking): bool
    {
        return $authUser->can('ForceDelete:Booking');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Booking');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Booking');
    }

    public function replicate(AuthUser $authUser, Booking $booking): bool
    {
        return $authUser->can('Replicate:Booking');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Booking');
    }
}
