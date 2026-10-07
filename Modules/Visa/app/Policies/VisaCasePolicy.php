<?php

declare(strict_types=1);

namespace Modules\Visa\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Visa\Models\VisaCase;

/**
 * Quyền theo tên quyền (không theo tên vai trò) — giống TourPolicy — cộng thêm
 * điều kiện người phụ trách:
 *  - có ViewAll:VisaCase (admin): xem / sửa mọi hồ sơ, giao hồ sơ cho người khác
 *  - nhân viên visa: xem hồ sơ của mình + hồ sơ chưa ai nhận (để bấm nhận);
 *    chỉ sửa / xoá hồ sơ của mình
 */
class VisaCasePolicy
{
    public function viewAny(AuthUser $u): bool
    {
        return $u->can('ViewAny:VisaCase');
    }

    public function view(AuthUser $u, VisaCase $hs): bool
    {
        return $u->can('View:VisaCase') && ($this->xemHet($u) || $hs->chuaAiNhan() || $this->cuaMinh($u, $hs));
    }

    public function create(AuthUser $u): bool
    {
        return $u->can('Create:VisaCase');
    }

    public function update(AuthUser $u, VisaCase $hs): bool
    {
        return $u->can('Update:VisaCase') && ($this->xemHet($u) || $this->cuaMinh($u, $hs));
    }

    public function delete(AuthUser $u, VisaCase $hs): bool
    {
        return $u->can('Delete:VisaCase') && ($this->xemHet($u) || $this->cuaMinh($u, $hs));
    }

    public function deleteAny(AuthUser $u): bool
    {
        return $u->can('DeleteAny:VisaCase');
    }

    /** Bấm "Nhận hồ sơ": hồ sơ phải còn trống và người bấm được sửa hồ sơ visa. */
    public function nhan(AuthUser $u, VisaCase $hs): bool
    {
        return $hs->chuaAiNhan() && $u->can('Update:VisaCase');
    }

    /** Đổi người phụ trách — chỉ người xem được mọi hồ sơ (admin). */
    public function giao(AuthUser $u): bool
    {
        return $this->xemHet($u);
    }

    private function xemHet(AuthUser $u): bool
    {
        return $u->can('ViewAll:VisaCase');
    }

    private function cuaMinh(AuthUser $u, VisaCase $hs): bool
    {
        return $hs->assigned_to !== null && (int) $hs->assigned_to === (int) $u->getAuthIdentifier();
    }
}
