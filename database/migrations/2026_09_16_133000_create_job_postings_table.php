<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('department')->nullable();       // Kinh doanh, Điều hành...
            $table->string('location')->nullable();         // TP.HCM
            $table->string('employment_type')->nullable();  // Toàn thời gian...
            $table->string('salary_range')->nullable();     // Thoả thuận / 10-15 triệu
            $table->unsignedSmallInteger('quantity')->nullable();
            $table->longText('description')->nullable();     // mô tả công việc
            $table->longText('requirements')->nullable();    // yêu cầu
            $table->longText('benefits')->nullable();        // quyền lợi
            $table->date('deadline')->nullable();
            $table->string('status')->default('published');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_postings');
    }
};
