<?php

namespace Modules\Page\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Page\Models\Page;
use Modules\Page\Models\Setting;

class PageDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $trang = [
            ['slug' => 'about', 'title' => 'Về chúng tôi'],
            ['slug' => 'contact', 'title' => 'Liên hệ'],
            ['slug' => 'privacy', 'title' => 'Chính sách bảo mật'],
            ['slug' => 'terms', 'title' => 'Điều khoản sử dụng'],
            ['slug' => 'payment-policy', 'title' => 'Chính sách thanh toán'],
            ['slug' => 'cancellation-policy', 'title' => 'Chính sách huỷ & hoàn tiền'],
            ['slug' => 'faq', 'title' => 'Câu hỏi thường gặp'],
        ];

        foreach ($trang as $t) {
            Page::firstOrCreate(
                ['slug' => $t['slug']],
                ['title' => $t['title'], 'is_system' => true, 'status' => 'published']
            );
        }

        $cauHinh = [
            ['key' => 'company_name', 'label' => 'Tên công ty', 'group' => 'general', 'type' => 'text', 'value' => 'PSV Travel'],
            ['key' => 'hotline', 'label' => 'Hotline', 'group' => 'contact', 'type' => 'text', 'value' => '1900 1177'],
            ['key' => 'email', 'label' => 'Email liên hệ', 'group' => 'contact', 'type' => 'text', 'value' => 'hi@psvtravel.vn'],
            ['key' => 'address', 'label' => 'Địa chỉ', 'group' => 'contact', 'type' => 'textarea'],
            ['key' => 'working_hours', 'label' => 'Giờ làm việc', 'group' => 'contact', 'type' => 'text'],
            ['key' => 'tax_code', 'label' => 'Mã số thuế', 'group' => 'general', 'type' => 'text'],
            ['key' => 'logo', 'label' => 'Logo', 'group' => 'general', 'type' => 'image'],
            ['key' => 'facebook', 'label' => 'Facebook', 'group' => 'social', 'type' => 'url'],
            ['key' => 'zalo', 'label' => 'Zalo', 'group' => 'social', 'type' => 'text'],
            ['key' => 'youtube', 'label' => 'YouTube', 'group' => 'social', 'type' => 'url'],
            ['key' => 'tiktok', 'label' => 'TikTok', 'group' => 'social', 'type' => 'url'],
            ['key' => 'seo_title', 'label' => 'Tiêu đề mặc định (SEO)', 'group' => 'seo', 'type' => 'text'],
            ['key' => 'seo_description', 'label' => 'Mô tả mặc định (SEO)', 'group' => 'seo', 'type' => 'textarea'],

            // ==== Khối pháp lý — hiển thị ở chân trang để khách kiểm chứng công ty có thật ====
            // Cố ý để trống value: đây là số liệu pháp lý thật, phải do người phụ trách
            // điền đúng theo giấy tờ. Điền sẵn số giả là vi phạm Luật Quảng cáo.
            ['key' => 'legal_name', 'label' => 'Tên pháp nhân đầy đủ', 'group' => 'legal', 'type' => 'text'],
            ['key' => 'business_registration', 'label' => 'Số ĐKKD (giấy phép kinh doanh)', 'group' => 'legal', 'type' => 'text'],
            ['key' => 'business_registration_place', 'label' => 'Nơi cấp ĐKKD', 'group' => 'legal', 'type' => 'text'],
            ['key' => 'license_number', 'label' => 'Số giấy phép lữ hành quốc tế', 'group' => 'legal', 'type' => 'text'],
            ['key' => 'license_issuer', 'label' => 'Cơ quan cấp giấy phép lữ hành', 'group' => 'legal', 'type' => 'text'],
            ['key' => 'founded_year', 'label' => 'Năm thành lập', 'group' => 'legal', 'type' => 'text'],
            ['key' => 'legal_representative', 'label' => 'Người đại diện pháp luật', 'group' => 'legal', 'type' => 'text'],
            ['key' => 'moit_url', 'label' => 'Link xác nhận Bộ Công Thương (online.gov.vn)', 'group' => 'legal', 'type' => 'url'],
            ['key' => 'branch_addresses', 'label' => 'Địa chỉ chi nhánh (mỗi dòng một chi nhánh)', 'group' => 'legal', 'type' => 'textarea'],
        ];

        foreach ($cauHinh as $i => $c) {
            Setting::firstOrCreate(
                ['key' => $c['key']],
                array_merge($c, ['sort_order' => $i]),
            );
        }
    }
}