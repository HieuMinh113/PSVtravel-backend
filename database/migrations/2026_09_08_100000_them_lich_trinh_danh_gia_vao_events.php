<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Chương trình theo ngày (mỗi ngày có tiêu đề, mô tả, nhiều ảnh) + điểm đánh giá
// trung bình. Lưu itinerary dạng JSON ngay trên events cho gọn, không cần bảng
// riêng như tour vì gói sự kiện thường ít ngày.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->json('itinerary')->nullable()->after('includes');
            $table->decimal('rating', 2, 1)->nullable()->after('is_featured');
            $table->unsignedInteger('review_count')->default(0)->after('rating');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['itinerary', 'rating', 'review_count']);
        });
    }
};
