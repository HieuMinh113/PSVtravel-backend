<?php

namespace Modules\Guide\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Quyền quản lý bản đồ cẩm nang (Bản đồ: Quốc gia, Bản đồ: Thành phố & điểm).
 * Cấp cho super_admin, admin và mọi vai trò đang sửa được bài Cẩm nang — ai
 * viết bài thì cũng ghim được bài lên bản đồ. Chạy lại bao nhiêu lần cũng được.
 *
 * Gọi từ migration (máy nào chạy migrate là có, không cần nhớ shield:generate)
 * VÀ từ RoleSeeder (máy mới cài: migration chạy trước khi có super_admin).
 */
class QuyenBanDoCamNangSeeder extends Seeder
{
    private const MODEL = ['GuideCountry', 'GuideCity', 'GuideSpot'];

    private const THAO_TAC = [
        'ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny', 'Restore',
        'ForceDelete', 'ForceDeleteAny', 'RestoreAny', 'Replicate', 'Reorder',
    ];

    /** @return list<string> */
    public static function quyen(): array
    {
        $ds = [];
        foreach (self::MODEL as $m) {
            foreach (self::THAO_TAC as $t) {
                $ds[] = "{$t}:{$m}";
            }
        }

        return $ds;
    }

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $quyen = collect(self::quyen())->map(fn ($q) => Permission::findOrCreate($q, 'web'));

        $vaiTro = Role::query()
            ->where('guard_name', 'web')
            ->where(fn ($q) => $q->whereIn('name', ['super_admin', 'admin'])
                ->orWhereHas('permissions', fn ($p) => $p->where('name', 'Update:Guide')))
            ->get();
        foreach ($vaiTro as $r) {
            $r->givePermissionTo($quyen);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
