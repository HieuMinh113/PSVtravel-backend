<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ảnh riêng cho từng ngày trong lịch trình.
 *
 * Trước đây trang chi tiết tour tự chia ảnh từ thư viện chung cho các ngày theo
 * vòng tròn — hết ảnh thì quay lại từ đầu, nên ngày 4 lại thấy ảnh của ngày 1.
 * Giờ nhân viên chỉ định thẳng ảnh nào thuộc ngày nào.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_itineraries', function (Blueprint $table) {
            $table->json('images')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('tour_itineraries', function (Blueprint $table) {
            $table->dropColumn('images');
        });
    }
};
