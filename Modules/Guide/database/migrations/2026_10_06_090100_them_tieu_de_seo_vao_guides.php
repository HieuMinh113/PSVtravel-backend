<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Tiêu đề SEO" — tiêu đề riêng cho Google (thẻ <title>), tối đa 70 ký tự.
 *
 * Tiêu đề bài cẩm nang thường dài, cộng đuôi " | PSV Travel" là bị Google cắt
 * cụt; công cụ audit SEO báo "Long title element". Trống thì website dùng tiêu
 * đề bài.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guides', function (Blueprint $table) {
            $table->string('seo_title', 70)->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('guides', function (Blueprint $table) {
            $table->dropColumn('seo_title');
        });
    }
};
