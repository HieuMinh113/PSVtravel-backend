<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Gói sự kiện / team building do admin tự thêm. Mỗi bản ghi là một "gói" giới
// thiệu (team building bãi biển, gala cuối năm, company trip...) — có ảnh, mô
// tả, danh sách "sẽ có gì". Khách xem rồi để lại yêu cầu qua form Team Building.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');                       // Tên gói / sự kiện
            $table->string('slug')->unique();
            $table->text('summary')->nullable();           // Mô tả ngắn ở thẻ danh sách
            $table->string('cover_image')->nullable();     // Ảnh bìa
            $table->json('gallery')->nullable();           // Thư viện ảnh (mảng đường dẫn)
            $table->longText('description')->nullable();    // Giới thiệu chi tiết (RichEditor)
            $table->json('includes')->nullable();          // "Sẽ có gì" — mảng dòng
            $table->string('group_size')->nullable();      // vd "20 – 200 khách"
            $table->string('duration')->nullable();        // vd "2 ngày 1 đêm"
            $table->string('location')->nullable();        // Địa điểm gợi ý
            $table->string('price_note')->nullable();      // vd "Liên hệ báo giá" / "Từ 850.000đ/khách"
            $table->boolean('is_featured')->default(false); // Nổi bật lên đầu
            $table->string('status')->default('published'); // published | hidden
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
