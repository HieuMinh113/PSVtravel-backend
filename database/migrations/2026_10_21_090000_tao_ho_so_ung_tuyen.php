<?php

use Database\Seeders\QuyenHoSoUngTuyenSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hồ sơ ứng tuyển gửi từ trang chi tiết tin tuyển dụng: thông tin liên hệ,
 * CV (PDF) và portfolio (PDF hoặc link, không bắt buộc). File nằm ở ổ riêng
 * tư "rieng" — chỉ người có quyền mở được trong trang quản trị.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_posting_id')->nullable()->constrained('job_postings')->nullOnDelete();
            $table->string('job_title');                    // giữ tên vị trí lúc nộp — tin có sửa / xoá vẫn biết
            $table->string('full_name');
            $table->string('phone', 30);
            $table->string('email');
            $table->text('message')->nullable();             // lời nhắn / giới thiệu ngắn
            $table->string('cv_path');
            $table->string('cv_name')->nullable();           // tên file gốc ứng viên đặt
            $table->string('portfolio_path')->nullable();
            $table->string('portfolio_name')->nullable();
            $table->string('portfolio_url', 500)->nullable(); // portfolio quá nặng: link Drive / Behance…
            $table->string('status', 30)->default('moi');
            $table->text('admin_note')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        // Quyền xem hồ sơ ứng tuyển — cấp cho super_admin, admin. Không cần
        // nhớ chạy shield:generate, menu "Hồ sơ ứng tuyển" hiện ngay.
        (new QuyenHoSoUngTuyenSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};
