<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Visa\Database\Seeders\MauChecklistVisaSeeder;

/**
 * - thong_tin: câu trả lời "Phiếu thông tin xin visa" (xem PhieuThongTin)
 * - thêm mẫu checklist đoàn Hàn Quốc (theo checklist "VISA ĐOÀN HÀN")
 *
 * File giấy tờ gắn theo từng dòng checklist nằm ngay trong JSON checklist
 * (khoá tep / ten_tep của mỗi dòng) nên không cần cột mới.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visa_cases', function (Blueprint $table) {
            $table->json('thong_tin')->nullable()->after('checklist');
        });

        MauChecklistVisaSeeder::themMauNeuChuaCo(MauChecklistVisaSeeder::mauDoanHan());
    }

    public function down(): void
    {
        Schema::table('visa_cases', function (Blueprint $table) {
            $table->dropColumn('thong_tin');
        });
    }
};
