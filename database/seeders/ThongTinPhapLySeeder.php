<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Page\Models\Setting;

/**
 * Thông tin pháp lý và liên hệ THẬT của công ty.
 *
 * Tách riêng khỏi PageDatabaseSeeder vì seeder kia dùng firstOrCreate
 * (chỉ tạo ô trống, không ghi đè). Seeder này cố ý GHI ĐÈ giá trị,
 * nên chỉ chạy khi muốn đặt lại thông tin công ty về đúng giấy tờ —
 * không nằm trong chuỗi seed mặc định.
 *
 *   php artisan db:seed --class=Database\Seeders\ThongTinPhapLySeeder
 *
 * Nguồn: Giấy chứng nhận đăng ký doanh nghiệp.
 */
class ThongTinPhapLySeeder extends Seeder
{
    public function run(): void
    {
        $thongTin = [
            // --- Pháp lý ---
            'legal_name' => 'CÔNG TY CỔ PHẦN DU LỊCH P.S.V TRAVEL',
            'company_name' => 'PSV Travel',
            'tax_code' => '0314542363',
            // Từ 2015 mã số doanh nghiệp trùng với mã số thuế, nên số ĐKKD
            // chính là dãy số này.
            'business_registration' => '0314542363',
            'business_registration_place' => 'Sở Kế hoạch và Đầu tư TP. Hồ Chí Minh',
            'legal_representative' => 'Nguyễn Anh Dũng',

            // --- Liên hệ ---
            'hotline' => '0907 870 707',
            'address' => '529 Huỳnh Tấn Phát, Phường Tân Thuận, Quận 7, TP. Hồ Chí Minh',
        ];

        foreach ($thongTin as $key => $value) {
            Setting::query()->where('key', $key)->update(['value' => $value]);
        }

        $this->command->info('Đã cập nhật thông tin pháp lý và liên hệ của công ty.');
        $this->command->newLine();
        $this->command->warn('Còn hai ô CỐ Ý để trống, điền trong admin khi có giấy tờ:');
        $this->command->line('  • Số giấy phép lữ hành  — Cục Du lịch / Sở Du lịch cấp, KHÁC giấy ĐKDN');
        $this->command->line('  • Link Bộ Công Thương   — có sau khi thông báo tại online.gov.vn');
    }
}
