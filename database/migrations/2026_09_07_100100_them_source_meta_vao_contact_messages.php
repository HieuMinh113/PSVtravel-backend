<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cho phép hộp thư "Tin liên hệ" nhận thêm yêu cầu Team Building mà không tách
// ra bảng/menu riêng. `source` phân loại nguồn tin; `meta` giữ các trường riêng
// của team building (công ty, số người, ngày, địa điểm, ngân sách) dưới dạng
// JSON để không phải đẻ thêm nhiều cột chỉ dùng cho một loại tin.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->string('source')->default('contact')->after('subject'); // contact | team_building
            $table->json('meta')->nullable()->after('message');
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn(['source', 'meta']);
        });
    }
};
