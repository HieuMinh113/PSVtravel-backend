<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Video minh hoạ cho bài cẩm nang — CHỈ lưu link YouTube.
 *
 * Dùng ô riêng thay vì dán iframe vào giữa nội dung: bộ lọc chống XSS của
 * website loại mọi iframe trong nội dung bài (đúng chủ ý), nên video phải đi
 * đường riêng, website tự dựng khung nhúng từ mã video đã kiểm tra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guides', function (Blueprint $table) {
            $table->string('video_url')->nullable()->after('cover_image');
        });
    }

    public function down(): void
    {
        Schema::table('guides', function (Blueprint $table) {
            $table->dropColumn('video_url');
        });
    }
};
