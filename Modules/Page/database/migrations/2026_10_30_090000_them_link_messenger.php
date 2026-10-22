<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Page\Models\Setting;

return new class extends Migration
{
    // Nút "Chat Messenger" nổi trên website (cạnh Zalo, Hotline). Để trống thì
    // nút tự ẩn — không dựng link m.me/ hỏng.
    public function up(): void
    {
        Setting::firstOrCreate(
            ['key' => 'messenger'],
            ['label' => 'Messenger (link m.me/… hoặc tên trang Facebook)', 'group' => 'social', 'type' => 'text', 'sort_order' => 99],
        );
    }

    public function down(): void
    {
        Setting::where('key', 'messenger')->delete();
    }
};
