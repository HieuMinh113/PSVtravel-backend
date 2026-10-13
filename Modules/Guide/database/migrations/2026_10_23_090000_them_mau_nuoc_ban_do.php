<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Màu tô của từng nước trên quả địa cầu (trang Cẩm nang), dạng #RRGGBB.
 * Trống = màu mặc định (xanh lá logo PSV).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guide_countries', function (Blueprint $table) {
            $table->string('color', 7)->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('guide_countries', fn (Blueprint $table) => $table->dropColumn('color'));
    }
};
