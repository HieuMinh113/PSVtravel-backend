<?php

declare(strict_types=1);

namespace App\Policies;

use App\Policies\Concerns\PhanQuyenNoiDung;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Như nội dung khác (admin, nhân viên), thêm Marketing: chuyển hướng link cũ là
 * việc giữ thứ hạng Google của web cũ, Marketing hay là người theo dõi.
 */
class RedirectPolicy
{
    use PhanQuyenNoiDung;

    private const SUA = ['super_admin', 'admin', 'staff', 'marketing'];

    public function viewAny(AuthUser $u): bool
    {
        return $u->hasAnyRole(self::SUA);
    }

    public function view(AuthUser $u, $record): bool
    {
        return $u->hasAnyRole(self::SUA);
    }

    public function create(AuthUser $u): bool
    {
        return $u->hasAnyRole(self::SUA);
    }

    public function update(AuthUser $u, $record): bool
    {
        return $u->hasAnyRole(self::SUA);
    }
}
