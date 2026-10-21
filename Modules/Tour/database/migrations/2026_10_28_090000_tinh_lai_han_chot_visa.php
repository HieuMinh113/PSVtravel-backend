<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Tour\Services\HanNopVisa;

/**
 * Tính hạn chót nộp visa cho mọi đợt đang có (tour_departures.visa_last_day) —
 * chạy sau khi đã đủ cột / bảng (2026_10_26, 2026_10_27). Cũng là lần tính lại
 * theo cách tính thống nhất: số ngày riêng của tour cũng "từ ngày bay − N ẩn"
 * như danh sách visa theo nước (trước đây hạn chót muộn hơn 1 ngày).
 */
return new class extends Migration
{
    public function up(): void
    {
        HanNopVisa::tinhLaiTatCa();
    }

    public function down(): void
    {
        // Không cần: lần lưu đợt / tour / danh sách tiếp theo tự tính theo mã đang chạy
    }
};
