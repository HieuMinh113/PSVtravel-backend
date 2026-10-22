<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Khách đến từ kênh nào (App\Services\NguonKhach): kenh để lọc / thống kê,
    // utm giữ dữ liệu thô (chiến dịch, mã clip, tên miền giới thiệu).
    // Đơn / tin nhắn cũ để trống = "không rõ".
    public function up(): void
    {
        foreach (['bookings', 'contact_messages'] as $bang) {
            Schema::table($bang, function (Blueprint $table) {
                $table->string('kenh', 30)->nullable()->index();
                $table->json('utm')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['bookings', 'contact_messages'] as $bang) {
            Schema::table($bang, function (Blueprint $table) {
                $table->dropIndex(['kenh']);
                $table->dropColumn(['kenh', 'utm']);
            });
        }
    }
};
