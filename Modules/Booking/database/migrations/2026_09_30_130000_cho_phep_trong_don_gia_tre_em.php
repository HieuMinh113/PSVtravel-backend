<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Đơn giá trẻ em được để TRỐNG = "chờ nhân viên báo giá".
 *
 * Tour không nhập giá trẻ em thì trước đây: website tự ước 60% giá người lớn
 * và cộng vào tạm tính, còn máy chủ lại tính trẻ em 0đ — khách thấy một số,
 * email/admin ghi số khác. Nay thống nhất: không có giá thì KHÔNG tính, ghi rõ
 * "chờ báo giá". Cần phân biệt với 0 = trẻ em miễn phí thật, nên dùng NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_price_child')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_price_child')->nullable(false)->default(0)->change();
        });
    }
};
