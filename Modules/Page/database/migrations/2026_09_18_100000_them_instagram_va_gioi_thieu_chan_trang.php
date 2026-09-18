<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Page\Models\Setting;

return new class extends Migration
{
    // Bổ sung ô Instagram (footer đang hiện icon này nhưng chưa có ô để sửa)
    // và ô "Giới thiệu ngắn (chân trang)" để admin sửa được dòng dưới logo.
    public function up(): void
    {
        Setting::firstOrCreate(
            ['key' => 'instagram'],
            ['label' => 'Instagram', 'group' => 'social', 'type' => 'url', 'sort_order' => 90],
        );

        Setting::firstOrCreate(
            ['key' => 'footer_intro'],
            [
                'label' => 'Giới thiệu ngắn (chân trang)',
                'group' => 'contact',
                'type' => 'textarea',
                'sort_order' => 91,
                'value' => 'Đồng hành cùng bạn trên mọi hành trình — từ những bãi biển Việt Nam trong xanh đến những vùng đất mới lạ khắp thế giới.',
            ],
        );
    }

    public function down(): void
    {
        Setting::whereIn('key', ['instagram', 'footer_intro'])->delete();
    }
};
