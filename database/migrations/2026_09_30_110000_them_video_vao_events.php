<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clip recap sự kiện / team building đã tổ chức — CHỈ lưu link YouTube.
 * Bằng chứng năng lực cho khách doanh nghiệp, không tốn ổ đĩa máy chủ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('video_url')->nullable()->after('cover_image');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('video_url');
        });
    }
};
