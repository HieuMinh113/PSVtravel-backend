<?php

namespace Modules\Booking\Policies;

use App\Models\User;
use Modules\Booking\Models\Payment;

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
        return $user->can('Update:Booking') || $user->can('Approve:Payment');
    }

    // Khoản kế toán đã duyệt / từ chối thì nhân viên không sửa được nữa
    public function update(User $user, ?Payment $payment = null): bool
    {
        if ($user->can('Approve:Payment')) {
            return true;
        }

        return $user->can('Update:Booking') && ($payment === null || $payment->status === 'pending');
    }

    /** Kế toán bấm "Đã nhận tiền" / "Từ chối". */
    public function duyet(User $user, Payment $payment): bool
    {
        return $payment->status === 'pending' && $user->can('Approve:Payment');
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
