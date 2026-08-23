<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gắn đánh giá vào ĐƠN thay vì chỉ gắn vào tour.
 *
 * Quy tắc cũ "mỗi tài khoản một đánh giá cho mỗi tour" khiến khách đi lại
 * cùng một tour ở đợt khác không viết được đánh giá thứ hai — trong khi đó là
 * hai chuyến đi riêng biệt, khác hướng dẫn viên, khác đoàn, khác mùa.
 *
 * Có booking_id thì quy tắc thành "mỗi đơn một đánh giá": đi hai lần viết được
 * hai bài, mà vẫn không ai gửi trùng cho cùng một chuyến.
 *
 * Để nullable vì các đánh giá cũ chưa gắn đơn nào.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->foreignId('booking_id')->nullable()->after('user_id')
                ->constrained()->nullOnDelete();

            // Một đơn chỉ được đánh giá một lần
            $table->unique('booking_id');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropUnique(['booking_id']);
            $table->dropConstrainedForeignId('booking_id');
        });
    }
};
