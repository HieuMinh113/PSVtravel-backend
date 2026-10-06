<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tuỳ chọn đọc từng sheet + kiểu dữ liệu mới gặp ở 10 sheet đối tác thật:
 *  - mau_do: ngày tô đỏ có nghĩa là hết chỗ không (Hanvina: có, theo chú thích)
 *  - tab_bo_qua: tab không phải lịch tour (phí visa, vé máy bay...)
 *  - cot_tay: khai cột bằng tay cho tab không có dòng tiêu đề
 *  - bao_cao: kết quả lần đọc gần nhất theo từng tab
 *  - Ngày đi "Thứ 5 hằng tuần" (VVT nội địa) không có ngày cụ thể → departure_date
 *    được để trống, ghi lịch vào weekly
 *  - Giá khuyến mãi: price là giá KM, price_original là giá gốc (gạch ngang)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alliance_sources', function (Blueprint $table) {
            $table->string('mau_do', 20)->default('tat')->after('is_active');
            $table->json('tab_bo_qua')->nullable()->after('mau_do');
            $table->json('cot_tay')->nullable()->after('tab_bo_qua');
            $table->json('bao_cao')->nullable()->after('warnings');
        });

        Schema::table('alliance_departures', function (Blueprint $table) {
            $table->date('departure_date')->nullable()->change();
            $table->string('weekly', 80)->nullable()->after('departure_date');
            $table->unsignedBigInteger('price_original')->nullable()->after('price');
            $table->string('tour_code', 60)->nullable()->after('alliance_source_id');
        });
    }

    public function down(): void
    {
        Schema::table('alliance_departures', function (Blueprint $table) {
            $table->dropColumn(['weekly', 'price_original', 'tour_code']);
        });
        Schema::table('alliance_sources', function (Blueprint $table) {
            $table->dropColumn(['mau_do', 'tab_bo_qua', 'cot_tay', 'bao_cao']);
        });
    }
};
