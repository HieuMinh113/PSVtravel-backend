<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Tour\Services\HanNopVisa;

/**
 * Tính lại hạn chót nộp visa đã lưu sẵn (tour_departures.visa_last_day) sau khi
 * thống nhất cách tính: số ngày riêng của tour cũng "từ ngày bay − N ẩn" như danh
 * sách visa theo nước (trước đây hạn chót muộn hơn 1 ngày).
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
