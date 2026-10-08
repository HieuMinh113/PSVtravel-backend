<?php

use Database\Seeders\VaiTroNhanVienSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Vai trò sale, kế toán, marketing, điều hành, visa với bộ quyền theo bộ phận
 * — chạy ở bước cập nhật CSDL của script triển khai nên máy chủ thật có ngay.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new VaiTroNhanVienSeeder)->run();
    }

    public function down(): void
    {
        // Không gỡ: vai trò có thể đã gán cho nhân viên thật
    }
};
