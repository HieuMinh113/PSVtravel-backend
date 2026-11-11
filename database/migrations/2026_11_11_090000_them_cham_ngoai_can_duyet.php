<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chấm công — lượt chấm vào / ra ở ngoài công ty (hoặc không lấy được vị trí)
 * phải được quản lý duyệt mới tính công. Cờ này đánh dấu lượt nào phải chờ
 * duyệt; các lượt chấm trước khi có cột này mặc định false nên giữ nguyên.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            foreach (['in', 'out'] as $p) {
                $table->boolean("{$p}_requires_approval")->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['in_requires_approval', 'out_requires_approval']);
        });
    }
};
