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
        ];

        foreach ($cauHinh as $i => $c) {
            Setting::firstOrCreate(
                ['key' => $c['key']],
                array_merge($c, ['sort_order' => $i]),
            );
        }
    }
}