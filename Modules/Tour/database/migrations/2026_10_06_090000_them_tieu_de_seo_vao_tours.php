<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Tiêu đề SEO" — tiêu đề riêng cho Google (thẻ <title>), tối đa 70 ký tự.
 *
 * Tên tour nhân viên nhập thường rất dài ("TOUR SIÊU TIẾT KIỆM: ĐÀ NẴNG – HỘI
 * AN – BÀ NÀ HILLS – ..."), cộng đuôi " | PSV Travel" là bị Google cắt cụt;
 * công cụ audit SEO báo "Long title element". Trống thì website dùng tên tour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->string('seo_title', 70)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn('seo_title');
        });
    }
};
