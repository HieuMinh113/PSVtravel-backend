<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liên minh: sheet chỗ trống của đối tác → tour → ngày khởi hành.
 *
 * Dữ liệu tour / ngày đi do máy tự đọc từ sheet mỗi vài phút và bị ghi đè ở
 * mỗi lần đọc; chỉ bảng nguồn (alliance_sources) là do điều hành nhập tay.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alliance_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');                       // tên đối tác
            $table->text('sheet_url');
            $table->text('contact')->nullable();          // người liên hệ, SĐT, Zalo
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('warnings')->nullable();          // cảnh báo lần đọc gần nhất
            $table->date('sheet_updated_on')->nullable();  // "Ngày cập nhật" ghi trong sheet
            $table->unsignedInteger('departures_count')->default(0);
            $table->timestamps();
        });

        Schema::create('alliance_tours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alliance_source_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);                     // nhận diện tour giữa các lần đọc
            $table->string('name');
            $table->text('details')->nullable();           // chữ đầy đủ ô tên (chuyến bay, điểm nhấn)
            $table->string('duration', 30)->nullable();
            $table->text('airline')->nullable();
            $table->string('sheet_tab')->nullable();
            $table->string('section')->nullable();         // mục trong tab: "THÁNG 10/2026", "HÀN QUỐC"
            $table->text('program_url')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['alliance_source_id', 'key']);
        });

        Schema::create('alliance_departures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alliance_tour_id')->constrained()->cascadeOnDelete();
            $table->foreignId('alliance_source_id')->constrained()->cascadeOnDelete();
            $table->date('departure_date');
            $table->unsignedBigInteger('price')->nullable();
            $table->string('price_text', 120)->nullable(); // chữ giá đúng như trong sheet
            $table->unsignedBigInteger('price_child')->nullable();
            $table->unsignedBigInteger('price_infant')->nullable();
            $table->unsignedBigInteger('commission')->nullable();
            $table->integer('seats_total')->nullable();
            $table->integer('seats_sold')->nullable();
            $table->integer('seats_hold')->nullable();
            $table->integer('seats_left')->nullable();     // null = sheet không ghi số chỗ
            $table->string('status', 20);                  // con_cho | het_cho | huy | tam_ngung | lien_he
            $table->text('note')->nullable();
            $table->string('visa_deadline', 40)->nullable();
            $table->string('sheet_tab')->nullable();
            $table->unsignedInteger('sheet_row')->nullable();
            $table->timestamps();

            $table->unique(['alliance_tour_id', 'departure_date']);
            $table->index(['departure_date', 'status']);
        });

        // Tour PSV nối với một tour liên minh → số chỗ các ngày đi trên
        // website tự lấy theo sheet của đối tác.
        Schema::table('tours', function (Blueprint $table) {
            $table->foreignId('alliance_tour_id')->nullable()->after('status')
                ->constrained('alliance_tours')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropConstrainedForeignId('alliance_tour_id');
        });
        Schema::dropIfExists('alliance_departures');
        Schema::dropIfExists('alliance_tours');
        Schema::dropIfExists('alliance_sources');
    }
};
