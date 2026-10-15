<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Giá trẻ em / em bé riêng theo đợt khởi hành (như giá riêng người lớn) — sheet
 * đối tác ghi giá từng ngày cho cả 3 loại khách.
 *
 * alliance_sync: giá trị lần cuối lấy từ sheet liên minh ghi vào đợt này (số chỗ,
 * giá). Nhờ đó biết nhân viên đã tự sửa giá hay chưa — tự sửa thì không ghi đè.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_departures', function (Blueprint $table) {
            $table->unsignedBigInteger('child_price_override')->nullable()->after('price_override');
            $table->unsignedBigInteger('infant_price_override')->nullable()->after('child_price_override');
            $table->json('alliance_sync')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('tour_departures', function (Blueprint $table) {
            $table->dropColumn(['child_price_override', 'infant_price_override', 'alliance_sync']);
        });
    }
};
