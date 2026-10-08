<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Alliance\Database\Seeders\QuyenLienMinhSeeder;
use Modules\Booking\Database\Seeders\QuyenDonTourSeeder;
use Modules\Visa\Database\Seeders\QuyenVisaSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Vai trò nhân viên theo bộ phận (yêu cầu 08/10/2026). Chỉ THÊM quyền, không
 * gỡ quyền quản trị đã tick tay trong "Vai trò". Chạy lại bao nhiêu lần cũng được.
 *
 * Mọi vai trò đều tự chấm công được (ai vào được trang quản trị là có trang
 * Chấm công). "Đơn đặt tour của mình": tạo, xác nhận, thêm khoản thu, sửa —
 * chỉ đơn mình phụ trách + đơn khách đặt web chưa ai nhận (BookingPolicy).
 */
class VaiTroNhanVienSeeder extends Seeder
{
    /** Đơn đặt tour: tạo, xác nhận, thêm khoản thu, sửa đơn của mình. */
    public const DON_CUA_MINH = ['ViewAny:Booking', 'View:Booking', 'Create:Booking', 'Update:Booking'];

    public const TOUR_XEM = ['ViewAny:Tour', 'View:Tour'];

    /** Xem / thêm / sửa tour, kể cả ngày khởi hành, lịch trình, ảnh (không xoá). */
    public const TOUR_SUA = ['ViewAny:Tour', 'View:Tour', 'Create:Tour', 'Update:Tour'];

    /** Xem + xuất file mẫu checklist visa để gửi khách (không sửa). */
    public const CHECKLIST_XEM = ['ViewAny:VisaChecklist', 'View:VisaChecklist'];

    /** Trang "Làm visa" trên website + đối tác nộp visa. */
    public const VISA_WEB = [
        'ViewAny:VisaCountry', 'View:VisaCountry', 'Create:VisaCountry', 'Update:VisaCountry', 'Delete:VisaCountry', 'DeleteAny:VisaCountry',
        'ViewAny:VisaProvider', 'View:VisaProvider', 'Create:VisaProvider', 'Update:VisaProvider', 'Delete:VisaProvider', 'DeleteAny:VisaProvider',
    ];

    /** @return array<string, array{ten: string, quyen: list<string>}> */
    public static function vaiTro(): array
    {
        return [
            'sale' => ['ten' => 'Sale', 'quyen' => [...self::DON_CUA_MINH, ...self::TOUR_XEM, ...self::CHECKLIST_XEM]],
            'ke_toan' => ['ten' => 'Kế toán', 'quyen' => [...QuyenDonTourSeeder::QUYEN_KE_TOAN, QuyenChamCongSeeder::QUYEN_QUAN_LY]],
            'marketing' => ['ten' => 'Marketing', 'quyen' => [...self::DON_CUA_MINH, ...self::TOUR_SUA]],
            'dieu_hanh' => ['ten' => 'Điều hành', 'quyen' => [...QuyenLienMinhSeeder::QUYEN, ...self::DON_CUA_MINH]],
            'visa' => ['ten' => 'Visa', 'quyen' => [...QuyenVisaSeeder::QUYEN, ...self::VISA_WEB, ...self::DON_CUA_MINH, ...self::TOUR_XEM]],
        ];
    }

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::vaiTro() as $ma => ['quyen' => $quyen]) {
            Role::findOrCreate($ma, 'web')->givePermissionTo(
                collect($quyen)->unique()->map(fn ($q) => Permission::findOrCreate($q, 'web'))
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
