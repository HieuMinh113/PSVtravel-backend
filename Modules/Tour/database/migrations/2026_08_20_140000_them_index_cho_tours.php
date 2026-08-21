<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng tours trước đây chỉ có unique trên slug. Mọi truy vấn danh sách tour
 * đều lọc theo status + type rồi sắp theo is_featured, sort_order — không có
 * index nào phục vụ, PostgreSQL phải quét toàn bảng mỗi lần.
 *
 * Vài chục tour thì chưa thấy gì, nhưng đây là truy vấn chạy ở MỌI trang danh
 * sách và cả trang chủ, nên để càng lâu càng khó gỡ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            // Đúng thứ tự cột như mệnh đề WHERE + ORDER BY hay dùng
            $table->index(['status', 'type', 'is_featured', 'sort_order'], 'tours_loc_sap_index');
        });

        Schema::table('tour_departures', function (Blueprint $table) {
            // Lọc đợt còn mở, chưa qua ngày, sắp theo ngày đi
            $table->index(['tour_id', 'status', 'start_date'], 'departures_loc_index');
        });

        Schema::table('bookings', function (Blueprint $table) {
            // Tra cứu đơn theo mã, và lịch sử đơn của một tài khoản
            $table->index('booking_code', 'bookings_ma_don_index');
            $table->index(['user_id', 'created_at'], 'bookings_cua_toi_index');
        });
    }

    public function down(): void
    {
        Schema::table('tours', fn (Blueprint $t) => $t->dropIndex('tours_loc_sap_index'));
        Schema::table('tour_departures', fn (Blueprint $t) => $t->dropIndex('departures_loc_index'));
        Schema::table('bookings', function (Blueprint $t) {
            $t->dropIndex('bookings_ma_don_index');
            $t->dropIndex('bookings_cua_toi_index');
        });
    }
};
