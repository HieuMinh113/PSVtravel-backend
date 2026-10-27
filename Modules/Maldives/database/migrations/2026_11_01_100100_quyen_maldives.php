<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Maldives\Database\Seeders\QuyenMaldivesSeeder;

/** Quyền mục Maldives cho admin + các bộ phận (sale, marketing, điều hành, kế toán) */
return new class extends Migration
{
    public function up(): void
    {
        (new QuyenMaldivesSeeder)->run();
    }

    public function down(): void
    {
        // Không gỡ: quyền có thể đã chỉnh tay trong "Vai trò"
    }
};
