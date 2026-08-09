<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();          // about, contact, privacy, terms...
            $table->string('title');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('hero_image')->nullable();
            $table->longText('body')->nullable();      // nội dung dạng văn bản (dùng ngay)
            $table->json('content')->nullable();       // các khối — nơi Puck sẽ ghi vào sau này
            $table->boolean('is_system')->default(false); // trang lõi, không cho xoá
            $table->string('status')->default('published');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
