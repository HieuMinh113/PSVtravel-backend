<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hồ sơ visa theo từng case (mỗi khách một hồ sơ) + mẫu checklist giấy tờ.
 *
 * Mẫu checklist giống các file trong thư mục Drive thủ tục visa: mỗi nước, mỗi
 * mục đích (du lịch / công tác / thăm thân…), mỗi đối tượng (nhân viên, chủ
 * doanh nghiệp, hưu trí…) có một danh sách giấy tờ riêng. Hồ sơ chép danh sách
 * đó về rồi sửa tuỳ case — không cố định — và đánh dấu từng giấy tờ đã nhận.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visa_checklists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('country')->nullable()->index();
            $table->string('purpose')->nullable();   // du_lich | cong_tac | tham_than | du_hoc | khac
            $table->string('profile')->nullable();   // nhan_vien | chu_doanh_nghiep | tu_do | huu_tri | hoc_sinh | khac
            $table->json('items')->nullable();       // [{nhom, ten, ghi_chu}]
            $table->text('note')->nullable();
            $table->json('attachments')->nullable(); // file mẫu: tờ khai, thư mời…
            $table->json('attachment_names')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('visa_cases', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique();

            // Khách
            $table->string('full_name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('passport_no')->nullable();
            $table->date('passport_expiry')->nullable();
            $table->string('group_name')->nullable();

            // Visa
            $table->string('country')->index();
            $table->string('purpose')->nullable();
            $table->string('profile')->nullable();
            $table->date('travel_date')->nullable();
            $table->foreignId('visa_provider_id')->nullable()->constrained('visa_providers')->nullOnDelete();
            $table->foreignId('visa_checklist_id')->nullable()->constrained('visa_checklists')->nullOnDelete();
            $table->json('checklist')->nullable();   // [{nhom, ten, ghi_chu, trang_thai}]
            $table->json('files')->nullable();
            $table->json('file_names')->nullable();

            // Tiến độ
            $table->string('status')->default('moi')->index();
            $table->date('submitted_on')->nullable();
            $table->dateTime('appointment_at')->nullable();
            $table->date('result_expected_on')->nullable();
            $table->date('result_on')->nullable();
            $table->date('visa_expiry')->nullable();

            // Tiền — mỗi case một giá, không lấy bảng giá cố định
            $table->unsignedBigInteger('fee')->default(0);
            $table->unsignedBigInteger('cost')->default(0);
            $table->unsignedBigInteger('paid')->default(0);

            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visa_cases');
        Schema::dropIfExists('visa_checklists');
    }
};
