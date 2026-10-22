<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Link của website WordPress cũ (psvtravel.com.vn chuyển sang psvtravel.com
    // giữ nguyên đường dẫn) → trang mới tương ứng. Hệ thống tự khớp khi có người
    // / bot gõ link cũ và lưu lại ở đây; nhân viên sửa được trong admin.
    public function up(): void
    {
        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('old_path')->unique();
            $table->string('new_path', 500)->nullable();
            // 301 = chuyển sang new_path · 410 = trang đã gỡ hẳn · 404 = chưa khớp được, chờ nhân viên
            $table->unsignedSmallInteger('status_code')->default(301);
            // tu_dong = máy khớp · tay = nhân viên đặt (máy không ghi đè)
            $table->string('source', 10)->default('tu_dong');
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['status_code', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
    }
};
