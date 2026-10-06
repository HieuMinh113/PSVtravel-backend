<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lượt quét mã QR của từng tour — để biết ấn phẩm in (tờ rơi, poster,
 * standee...) có thật sự kéo được khách vào xem tour hay không.
 *
 * Mỗi lượt chỉ lưu tour nào, lúc nào, quét bằng điện thoại hay máy tính.
 * KHÔNG lưu IP hay thông tin nhận dạng người quét.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_qr_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained('tours')->cascadeOnDelete();
            $table->string('thiet_bi', 20)->nullable(); // dien_thoai | may_tinh
            $table->timestamp('scanned_at')->useCurrent();

            // Thống kê theo tour trong khoảng thời gian (hôm nay / 7 / 30 ngày)
            $table->index(['tour_id', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_qr_scans');
    }
};
