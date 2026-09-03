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

            // Giấy phép kinh doanh dịch vụ lữ hành quốc tế (INBOUND + OUTBOUND),
            // cấp lần 5, tháng 7/2026. Đây là giấy phép BẮT BUỘC phải công bố
            // với doanh nghiệp bán tour ra nước ngoài — khác hoàn toàn giấy
            // đăng ký doanh nghiệp ở trên.
            'license_number' => '79-769/2020/CDLQGVN-GP LHQT',
            'license_issuer' => 'Cục Du lịch Quốc gia Việt Nam',

            // Không còn hiện ở chân trang (giấy phép lữ hành nói lên nhiều hơn),
            // nhưng vẫn giữ lại để dùng cho hợp đồng và hoá đơn.
            'legal_representative' => 'Nguyễn Anh Dũng',

            // --- Liên hệ ---
            'hotline' => '0907 870 707',
            'address' => '529 Huỳnh Tấn Phát, Phường Tân Thuận, Quận 7, TP. Hồ Chí Minh',
        ];

        // updateOrCreate chứ không phải update().
        //
        // update() chỉ sửa dòng ĐÃ CÓ. Trên máy chủ mới toanh, bảng settings còn
        // rỗng nên mọi câu update khớp 0 dòng — seeder vẫn báo "đã cập nhật" mà
        // thực tế không ghi được gì, cả khối pháp lý ở chân trang trống trơn.
        foreach ($thongTin as $key => $value) {
            Setting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value],
            );
        }

        $this->command->info('Đã cập nhật thông tin pháp lý và liên hệ của công ty.');
        $this->command->newLine();
        $this->command->warn('Còn một ô CỐ Ý để trống, điền trong admin khi có:');
        $this->command->line('  • Link Bộ Công Thương   — có sau khi thông báo tại online.gov.vn');
    }
}
