<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Page\Models\Setting;

return new class extends Migration
{
    // Video giới thiệu đội ngũ / văn phòng / hành trình công ty trên trang
    // "Về chúng tôi". Để trống link thì khối tự ẩn.
    public function up(): void
    {
        Setting::firstOrCreate(
            ['key' => 'about_video_url'],
            ['label' => 'Video trang Về chúng tôi (link YouTube)', 'group' => 'general', 'type' => 'youtube', 'sort_order' => 97],
        );

        Setting::firstOrCreate(
            ['key' => 'about_video_title'],
            [
                'label' => 'Tiêu đề khối video Về chúng tôi',
                'group' => 'general',
                'type' => 'text',
                'sort_order' => 98,
                'value' => 'Gặp gỡ đội ngũ PSV Travel',
            ],
        );
    }

    public function down(): void
    {
        Setting::whereIn('key', ['about_video_url', 'about_video_title'])->delete();
    }
};
