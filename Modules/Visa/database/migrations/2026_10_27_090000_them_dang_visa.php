<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dạng visa (visa đoàn / visa lẻ / visa điện tử / giấy thông hành): cùng một nước,
 * mỗi dạng một số ngày nộp ("Trung Quốc — visa đoàn 7 ngày, visa lẻ 10 ngày").
 * Tour chọn dạng visa của mình; để trống thì lấy số ngày lớn nhất của nước đó.
 *
 * Các dòng đã có (chưa có dạng) thành "Visa đoàn".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visa_deadlines', function (Blueprint $table) {
            $table->string('kind', 20)->default('doan')->after('country');
            $table->dropUnique(['country']);
            $table->unique(['country', 'kind']);
        });

        Schema::table('tours', function (Blueprint $table) {
            $table->string('visa_kind', 20)->nullable()->after('visa_deadline_days');
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn('visa_kind');
        });

        // Mỗi nước chỉ giữ lại dòng số ngày lớn nhất, để đặt lại ràng buộc "một nước một dòng"
        $giu = DB::table('visa_deadlines')->orderByDesc('days')->orderBy('id')->get(['id', 'country'])
            ->unique('country')->pluck('id');
        DB::table('visa_deadlines')->whereNotIn('id', $giu)->delete();

        Schema::table('visa_deadlines', function (Blueprint $table) {
            $table->dropUnique(['country', 'kind']);
            $table->dropColumn('kind');
            $table->unique(['country']);
        });
    }
};
