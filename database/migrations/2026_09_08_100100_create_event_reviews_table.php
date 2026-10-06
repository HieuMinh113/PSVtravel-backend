<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Đánh giá của khách cho từng gói sự kiện / team building. Khác đánh giá tour
// (bắt buộc đăng nhập + đã đặt tour): khách team building đến từ form liên hệ,
// không có tài khoản/booking, nên cho gửi công khai rồi admin duyệt mới hiện.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('customer_name');
            $table->unsignedTinyInteger('rating');          // 1..5
            $table->text('content')->nullable();
            $table->string('status')->default('pending');   // pending | approved | rejected
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('admin_reply')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_reviews');
    }
};
