<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('destinations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('image')->nullable();
            $table->text('summary')->nullable();          // mô tả ngắn ở thẻ
            $table->longText('description')->nullable();   // giới thiệu SEO trang chi tiết
            $table->string('region')->nullable();          // Miền Trung, Đông Nam Á...
            $table->string('category_slug')->nullable();   // danh mục tour để lấy tour liên quan
            $table->boolean('is_featured')->default(false);
            $table->string('status')->default('published');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('destinations');
    }
};
