<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Đội ngũ / ban lãnh đạo hiển thị ở trang "Về chúng tôi". Admin tự thêm người
// thật (họ tên, chức vụ, email, ảnh) thay cho dữ liệu mẫu viết cứng trước đây.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->string('name');                 // Họ tên
            $table->string('position');             // Chức vụ
            $table->string('email')->nullable();    // Email liên hệ
            $table->string('photo')->nullable();    // Ảnh
            $table->text('bio')->nullable();        // Giới thiệu ngắn (cho phần chữ carousel)
            $table->string('status')->default('published'); // published | hidden
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_members');
    }
};
