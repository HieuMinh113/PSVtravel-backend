<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lấy ngày đi + số chỗ + giá từ sheet liên minh sang lịch khởi hành tour PSV:
 *  - default_seats: số chỗ mở trên web cho ngày sheet không ghi số chỗ (đa số đối
 *    tác chỉ đánh dấu "Full" khi hết)
 *  - price_net: sheet ghi giá NET (VNA "NET THU VỀ") — không phải giá bán, không
 *    tự điền làm giá đợt khởi hành
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alliance_sources', function (Blueprint $table) {
            $table->unsignedSmallInteger('default_seats')->default(10)->after('mau_do');
        });

        Schema::table('alliance_departures', function (Blueprint $table) {
            $table->boolean('price_net')->default(false)->after('price_original');
        });
    }

    public function down(): void
    {
        Schema::table('alliance_departures', function (Blueprint $table) {
            $table->dropColumn('price_net');
        });
        Schema::table('alliance_sources', function (Blueprint $table) {
            $table->dropColumn('default_seats');
        });
    }
};
