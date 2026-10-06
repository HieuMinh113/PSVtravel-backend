<?php

declare(strict_types=1);

namespace Modules\Alliance\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Alliance\Models\AllianceDeparture;

/** Ngày đi liên minh do máy đọc từ sheet: chỉ xem, không ai thêm / sửa / xoá tay. */
class AllianceDeparturePolicy
{
    public function viewAny(AuthUser $u): bool
    {
        return $u->can('ViewAny:AllianceDeparture');
    }

    public function view(AuthUser $u, AllianceDeparture $d): bool
    {
        return $u->can('View:AllianceDeparture');
    }

    public function create(AuthUser $u): bool
    {
        return false;
    }

    public function update(AuthUser $u, AllianceDeparture $d): bool
    {
        return false;
    }

    public function delete(AuthUser $u, AllianceDeparture $d): bool
    {
        return false;
    }

    public function deleteAny(AuthUser $u): bool
    {
        return false;
    }
}
