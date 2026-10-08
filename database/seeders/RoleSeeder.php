<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Modules\Alliance\Database\Seeders\QuyenLienMinhSeeder;
use Modules\Booking\Database\Seeders\QuyenDonTourSeeder;
use Modules\Visa\Database\Seeders\QuyenVisaSeeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['super_admin', 'admin', 'staff', 'customer'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@psvtravel.com'],
            [
                'name' => 'Quản trị viên PSVTravel',
                'password' => Hash::make('Admin@123456'),
                'email_verified_at' => now(),
                'locale' => 'vi',
            ]
        );

        $admin->syncRoles(['super_admin']);

        // Tài khoản nhân viên quyền hạn chế — dùng để kiểm thử phân quyền
        // (ca AUTH-04, AUTH-05 trong kịch bản kiểm thử): người này chỉ được
        // xử lý đơn và đánh giá, không được sửa tour hay đụng vào Cài đặt.
        $staff = User::firstOrCreate(
            ['email' => 'nhanvien@psvtravel.com'],
            [
                'name' => 'Nhân viên kinh doanh',
                'password' => Hash::make('NhanVien@123456'),
                'email_verified_at' => now(),
                'locale' => 'vi',
            ]
        );

        $staff->syncRoles(['staff']);

        // Quyền liên minh cho super_admin/admin + vai trò điều hành. Migration
        // cũng tạo, nhưng trên máy mới cài nó chạy TRƯỚC khi có super_admin.
        $this->call(QuyenLienMinhSeeder::class);
        // Tương tự cho vai trò visa (hồ sơ visa + mẫu checklist)
        $this->call(QuyenVisaSeeder::class);
        // Quyền quản lý đơn tour + vai trò kế toán (duyệt khoản thu)
        $this->call(QuyenDonTourSeeder::class);
        // Quản lý chấm công (xem / duyệt chấm công của mọi người)
        $this->call(QuyenChamCongSeeder::class);

        // Tạo đủ quyền cho mọi mục quản trị (Tour, Đơn đặt tour, Vé máy bay,
        // Người dùng, Cấu hình...) và cấp hết cho super_admin. Thiếu bước này
        // thì máy mới cài chỉ thấy vài mục — đã xảy ra với máy kiểm thử
        // 07/10/2026. Chỉ THÊM quyền cho super_admin, không đụng vai trò khác.
        Artisan::call('shield:generate', [
            '--all' => true,
            '--panel' => 'admin',
            '--option' => 'permissions',
            '--no-interaction' => true,
        ]);
    }
}
