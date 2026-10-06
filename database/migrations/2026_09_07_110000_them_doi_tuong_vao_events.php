<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Đối tượng phù hợp của gói: gia đình / doanh nghiệp / cá nhân. Lưu mảng JSON
// vì một gói có thể phù hợp nhiều nhóm cùng lúc.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->json('audience')->nullable()->after('summary');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('audience');
        });
    }
};
