<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            // Một bảng banner dùng cho nhiều chỗ trên web, phân biệt bằng vị trí:
            //   promo          — banner khuyến mãi ngang ở trang chủ (mặc định, như cũ)
            //   orbit_home     — ảnh trong vòng xoay ở trang chủ
            //   orbit_domestic — ảnh trong vòng xoay ở trang tour trong nước
            //   orbit_abroad   — ảnh trong vòng xoay ở trang tour nước ngoài
            $table->string('position')->default('promo')->after('id')->index();
        });

        // Ảnh vòng xoay không cần tiêu đề, nên bỏ ràng buộc bắt buộc
        Schema::table('banners', function (Blueprint $table) {
            $table->string('title')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
