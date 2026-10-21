<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\EditRole as ShieldEditRole;
use Illuminate\Support\Collection;

/**
 * Lưu vai trò mà KHÔNG làm mất quyền không có ô trên màn hình.
 *
 * Shield lưu bằng cách thay toàn bộ quyền của vai trò bằng các ô đang tích.
 * Quyền không có ô (VD quyền điểm bản đồ — điểm không có trang riêng) bị xoá
 * âm thầm: trước đây mở vai trò Kế toán, không đổi gì, bấm Lưu là mất quyền
 * duyệt khoản thu, xem mọi đơn, xem chấm công mọi người.
 */
class EditRole extends ShieldEditRole
{
    protected static string $resource = RoleResource::class;

    /** Quyền vai trò đang có mà màn hình không hiện ô → giữ nguyên sau khi lưu. */
    protected Collection $quyenGiuLai;

    protected function beforeSave(): void
    {
        $coO = FilamentShield::getEntitiesPermissions() ?? [];
        $this->quyenGiuLai = $this->record->permissions->filter(fn ($q) => ! in_array($q->name, $coO, true))->values();
    }

    protected function afterSave(): void
    {
        parent::afterSave();

        if ($this->quyenGiuLai->isNotEmpty()) {
            $this->record->givePermissionTo($this->quyenGiuLai);
        }
    }
}
