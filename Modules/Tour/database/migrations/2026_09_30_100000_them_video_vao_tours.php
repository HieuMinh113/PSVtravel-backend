<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Video giới thiệu cho từng tour — CHỈ lưu link YouTube, không lưu tệp video.
 *
 * Video nặng gấp hàng trăm lần ảnh: lưu thẳng lên VPS là đầy ổ và nghẽn băng
 * thông. Để YouTube phát, website chỉ nhúng — không tốn gì của máy chủ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->string('video_url')->nullable()->after('cover_image');
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn('video_url');
        });
    }
};
