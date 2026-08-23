<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chặn xoá vĩnh viễn một tour đang có đơn đặt.
 *
 * Trước đây khoá ngoại bookings.tour_id đặt cascadeOnDelete: xoá vĩnh viễn một
 * tour là cơ sở dữ liệu xoá sạch mọi đơn của tour đó — mất trắng lịch sử giao
 * dịch, không có cách nào lấy lại. Đây là dữ liệu kinh doanh và chứng từ đối
 * soát với khách, không được phép biến mất vì một cú bấm nhầm.
 *
 * Đổi sang restrictOnDelete: cơ sở dữ liệu từ chối xoá, tour buộc phải giữ lại
 * (hoặc chỉ xoá mềm — đơn vẫn đọc được tên tour nhờ withTrashed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['tour_id']);
            $table->foreign('tour_id')->references('id')->on('tours')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['tour_id']);
            $table->foreign('tour_id')->references('id')->on('tours')->cascadeOnDelete();
        });
    }
};
