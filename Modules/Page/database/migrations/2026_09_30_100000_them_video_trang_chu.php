<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Page\Models\Setting;

return new class extends Migration
{
    // Khối "Video giới thiệu" trên trang chủ: admin dán link YouTube + tiêu đề
    // trong Cài đặt. Để trống link thì khối tự ẩn.
    public function up(): void
    {
        Setting::firstOrCreate(
            ['key' => 'home_video_url'],
            ['label' => 'Video trang chủ (link YouTube)', 'group' => 'general', 'type' => 'youtube', 'sort_order' => 95],
        );

        Setting::firstOrCreate(
            ['key' => 'home_video_title'],
            [
                'label' => 'Tiêu đề khối video trang chủ',
                'group' => 'general',
                'type' => 'text',
                'sort_order' => 96,
                'value' => 'Hành trình cùng PSV Travel',
            ],
        );
    }

    public function down(): void
    {
        Setting::whereIn('key', ['home_video_url', 'home_video_title'])->delete();
    }
};
