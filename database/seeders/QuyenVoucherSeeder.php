<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Quyền voucher theo bộ phận. Chỉ THÊM quyền, chạy lại bao nhiêu lần cũng được.
 * Ai được quét voucher do quyền "Quét voucher" (Scan:Voucher) quyết định — tích
 * cho vai trò nào ở Hệ thống → Vai trò thì vai trò đó quét được.
 */
class QuyenVoucherSeeder extends Seeder
{
    private const HANH_DONG = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny', 'Restore', 'RestoreAny',
        'ForceDelete', 'ForceDeleteAny', 'Replicate', 'Reorder'];

    public const QUET = 'Scan:Voucher';

    private const XEM = ['ViewAny:Voucher', 'View:Voucher', 'ViewAny:VoucherBatch', 'View:VoucherBatch'];

    /** @return array<string, list<string>> vai trò → quyền */
    public static function theoVaiTro(): array
    {
        $tatCa = [self::QUET];
        foreach (['VoucherBatch', 'Voucher'] as $m) {
            foreach (self::HANH_DONG as $h) {
                $tatCa[] = "{$h}:{$m}";
            }
        }

        return [
            'super_admin' => $tatCa,
            'admin' => $tatCa,
            // tạo đợt, tạo mã, in, huỷ mã
            'marketing' => [...self::XEM, 'Create:VoucherBatch', 'Update:VoucherBatch', 'Update:Voucher', self::QUET],
            'sale' => [...self::XEM, self::QUET],
            'dieu_hanh' => [...self::XEM, self::QUET],
            'ke_toan' => self::XEM,
        ];
    }

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::theoVaiTro() as $vaiTro => $quyen) {
            // Vai trò bộ phận chưa có (máy mới cài) thì bỏ qua — RoleSeeder gọi lại sau
            Role::where('name', $vaiTro)->where('guard_name', 'web')->first()
                ?->givePermissionTo(collect($quyen)->map(fn ($q) => Permission::findOrCreate($q, 'web')));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
