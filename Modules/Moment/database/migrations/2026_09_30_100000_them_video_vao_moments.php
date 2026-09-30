<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Video cảm nhận của du khách trong "Khoảnh khắc du khách".
 *
 * Luồng: khách gửi clip (Zalo/Facebook) → nhân viên đăng lên YouTube → dán link
 * vào khoảnh khắc, chọn "Đang hiển thị" là lên web (chọn "Đã ẩn" = chưa duyệt).
 *
 * Ảnh chính chuyển sang KHÔNG bắt buộc: khoảnh khắc dạng video có thể không có
 * ảnh riêng — khi đó website dùng ảnh thu nhỏ (thumbnail) của chính video.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('moments', function (Blueprint $table) {
            $table->string('video_url')->nullable()->after('gallery');
            $table->string('image')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('moments', function (Blueprint $table) {
            $table->dropColumn('video_url');
        });
    }
};
