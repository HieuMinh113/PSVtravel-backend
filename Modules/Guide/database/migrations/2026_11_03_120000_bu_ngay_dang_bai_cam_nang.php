<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bài cẩm nang đã đăng mà thiếu "Thời điểm đăng" (form hứa tự điền nhưng trước
 * đây không điền) → lấy ngày tạo bài. Chỉ điền ô đang trống, không đổi ngày
 * bài nào đã đặt; bài nháp / đã ẩn để nguyên (khi đăng sẽ tự lấy giờ lúc đăng).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('guides')
            ->where('status', 'published')
            ->whereNull('published_at')
            ->update(['published_at' => DB::raw('COALESCE(created_at, updated_at, CURRENT_TIMESTAMP)')]);
    }

    public function down(): void
    {
        // Không hoàn tác: không còn biết bài nào trước đây để trống
    }
};
