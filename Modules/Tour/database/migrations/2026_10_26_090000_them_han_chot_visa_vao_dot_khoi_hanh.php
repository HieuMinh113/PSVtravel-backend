<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hạn chót nộp hồ sơ visa của từng đợt (hạn sớm nhất trong: hạn riêng của đợt,
 * quy tắc của tour, hạn theo nước của nhân viên visa) — lưu sẵn để website lọc
 * đợt quá hạn bằng SQL. Xem Modules\Tour\Services\HanNopVisa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_departures', function (Blueprint $table) {
            $table->date('visa_last_day')->nullable()->after('visa_deadline')->index();
        });
    }

    public function down(): void
    {
        Schema::table('tour_departures', function (Blueprint $table) {
            $table->dropIndex(['visa_last_day']);
            $table->dropColumn('visa_last_day');
        });
    }
};
