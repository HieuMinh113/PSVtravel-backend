<?php

namespace App\Policies;

use App\Models\User;

/**
 * Bảng chấm công / khuôn mặt của MỌI nhân viên: chỉ quản lý chấm công
 * (quyền ViewAll:Attendance). Nhân viên tự chấm và xem của mình ở trang
 * "Chấm công" — không qua đây.
 */
class AttendancePolicy
{
    private function quanLy(User $u): bool
    {
        return $u->can('ViewAll:Attendance');
    }

    public function viewAny(User $u): bool
    {
        return $this->quanLy($u);
    }

    public function view(User $u): bool
    {
        return $this->quanLy($u);
    }

    public function create(User $u): bool
    {
        return false;
    }

    public function update(User $u): bool
    {
        return $this->quanLy($u);
    }

    public function delete(User $u): bool
    {
        return $this->quanLy($u);
    }

    public function deleteAny(User $u): bool
    {
        return false;
    }
}
