<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Guide\Database\Seeders\QuyenBanDoCamNangSeeder;

/**
 * Quyền cho hai mục quản trị "Bản đồ: Quốc gia", "Bản đồ: Thành phố & điểm".
 * Thiếu bước này thì menu không hiện (kể cả với admin) cho tới khi ai đó nhớ
 * chạy shield:generate — đúng chuyện đã xảy ra trên máy local 13/10/2026.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new QuyenBanDoCamNangSeeder)->run();
    }

    public function down(): void
    {
        // Không gỡ quyền: có thể đã cấp tay cho vai trò khác
    }
};
