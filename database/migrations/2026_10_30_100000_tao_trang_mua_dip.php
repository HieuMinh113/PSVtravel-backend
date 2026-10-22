<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Trang tour theo mùa / dịp (/tour-tet, /tour-he, /tour-30-4…): mỗi trang là
    // một khoảng ngày khởi hành; tour có đợt đi trong khoảng đó tự hiện lên trang.
    public function up(): void
    {
        Schema::create('season_pages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            // null = cả tour trong nước lẫn nước ngoài
            $table->string('type')->nullable();
            $table->date('start_from');
            $table->date('start_to');
            $table->text('intro')->nullable();
            $table->json('faqs')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 300)->nullable();
            $table->string('status')->default('published');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_pages');
    }
};
