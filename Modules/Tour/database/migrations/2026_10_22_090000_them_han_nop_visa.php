<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hạn nộp hồ sơ visa (tour nước ngoài), hai tầng:
 *  - tours.visa_deadline_days: quy tắc chung "trước ngày khởi hành N ngày" —
 *    nhập một lần, mọi ngày khởi hành tự có hạn
 *  - tour_departures.visa_deadline: hạn riêng của một ngày khởi hành (đối tác
 *    chốt ngày cụ thể) — có thì dùng ngày này thay cho quy tắc chung
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->unsignedSmallInteger('visa_deadline_days')->nullable()->after('departure_from');
        });
        Schema::table('tour_departures', function (Blueprint $table) {
            $table->date('visa_deadline')->nullable()->after('start_date');
        });
    }

    public function down(): void
    {
        Schema::table('tour_departures', fn (Blueprint $table) => $table->dropColumn('visa_deadline'));
        Schema::table('tours', fn (Blueprint $table) => $table->dropColumn('visa_deadline_days'));
    }
};
