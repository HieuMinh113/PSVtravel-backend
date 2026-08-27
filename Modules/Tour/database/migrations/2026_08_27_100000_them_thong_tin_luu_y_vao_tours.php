<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Khối "Những thông tin cần lưu ý" trên trang tour.
 *
 * Mỗi tour tự giữ danh sách riêng: [{ "title": "...", "content": "..." }, ...]
 * Dùng một cột JSON thay vì mỗi mục một cột, vì số mục và tên mục do nhân viên
 * tự đặt — tour Tết cần thêm mục mà tour thường không có.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->json('notes')->nullable()->after('cancellation_policy');
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
