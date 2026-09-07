<?php

namespace Database\Seeders;

use App\Models\Event;
use Illuminate\Database\Seeder;

// Dữ liệu mẫu để xem thử trang Team Building trên máy dev.
// KHÔNG nằm trong DatabaseSeeder nên deploy production không tự chạy —
// chạy tay khi cần: php artisan db:seed --class=EventSeeder
class EventSeeder extends Seeder
{
    public function run(): void
    {
        $mau = [
            [
                'title' => 'Team Building Bãi Biển 2N1Đ',
                'slug' => 'team-building-bai-bien-2n1d',
                'summary' => 'Gói team building biển trọn gói cho doanh nghiệp: trò chơi vận động, gala dinner và nghỉ dưỡng.',
                'includes' => [
                    'MC & ê-kíp trò chơi team building chuyên nghiệp',
                    'Âm thanh, sân khấu, đạo cụ trò chơi',
                    'Gala dinner buổi tối kèm chương trình văn nghệ',
                    'Xe đưa đón, khách sạn, ăn uống theo chương trình',
                    'Bảo hiểm du lịch, y tế theo đoàn',
                ],
                'group_size' => '30 – 200 khách',
                'duration' => '2 ngày 1 đêm',
                'location' => 'Vũng Tàu, Phan Thiết, Nha Trang',
                'price_note' => 'Liên hệ báo giá',
                'is_featured' => true,
                'sort_order' => 1,
            ],
            [
                'title' => 'Gala Dinner & Year End Party',
                'slug' => 'gala-dinner-year-end-party',
                'summary' => 'Tổ chức tiệc cuối năm, vinh danh nhân viên, kết hợp ăn uống và giải trí trọn gói.',
                'includes' => [
                    'Lên kịch bản & dẫn chương trình',
                    'Sân khấu, âm thanh, ánh sáng, màn hình LED',
                    'Tiết mục văn nghệ, ban nhạc, MC',
                    'Set menu tiệc theo yêu cầu',
                    'Quay phim, chụp hình sự kiện',
                ],
                'group_size' => '50 – 500 khách',
                'duration' => '1 buổi tối',
                'location' => 'Nhà hàng, khách sạn tại TP. HCM',
                'price_note' => 'Liên hệ báo giá',
                'is_featured' => false,
                'sort_order' => 2,
            ],
            [
                'title' => 'Company Trip Đà Lạt 3N2Đ',
                'slug' => 'company-trip-da-lat-3n2d',
                'summary' => 'Chuyến đi gắn kết cho công ty: nghỉ dưỡng, tham quan và hoạt động nhóm nhẹ nhàng.',
                'includes' => [
                    'Xe đưa đón khứ hồi',
                    'Khách sạn 3–4 sao trung tâm Đà Lạt',
                    'Hướng dẫn viên suốt tuyến',
                    'Hoạt động gắn kết nhóm buổi tối',
                    'Vé tham quan các điểm trong chương trình',
                ],
                'group_size' => '20 – 120 khách',
                'duration' => '3 ngày 2 đêm',
                'location' => 'Đà Lạt',
                'price_note' => 'Từ 2.850.000đ/khách',
                'is_featured' => false,
                'sort_order' => 3,
            ],
        ];

        foreach ($mau as $e) {
            Event::updateOrCreate(['slug' => $e['slug']], $e);
        }
    }
}
