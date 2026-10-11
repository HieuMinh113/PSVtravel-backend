<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bản đồ cẩm nang: quả địa cầu → nước → thành phố → điểm → bài cẩm nang.
 *
 *  - guide_countries: nước có cẩm nang (mã ADM0_A3 khớp file ranh giới
 *    Modules/Guide/resources/geo/quoc-gia.json)
 *  - guide_cities:    thành phố trong nước, toạ độ + mức zoom khi mở bản đồ
 *  - guide_spots:     điểm trên bản đồ thành phố (ăn uống, tham quan…), bấm
 *                     vào thì mở bài cẩm nang gắn kèm
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_countries', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('intro')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('status')->default('published');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('guide_cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guide_country_id')->constrained('guide_countries')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->decimal('zoom', 4, 2)->default(11.5);
            $table->text('intro')->nullable();
            $table->string('cover_image')->nullable();
            $table->foreignId('guide_id')->nullable()->constrained('guides')->nullOnDelete(); // bài tổng quan
            $table->string('status')->default('published');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('guide_spots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guide_city_id')->constrained('guide_cities')->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('tham_quan');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->string('summary', 300)->nullable();
            $table->string('image')->nullable();
            $table->foreignId('guide_id')->nullable()->constrained('guides')->nullOnDelete();
            $table->string('status')->default('published');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_spots');
        Schema::dropIfExists('guide_cities');
        Schema::dropIfExists('guide_countries');
    }
};
