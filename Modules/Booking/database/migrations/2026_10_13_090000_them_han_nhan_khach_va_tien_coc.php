<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Đơn đặt tour:
 *  - created_by: nhân viên tạo đơn — người nhận chuông "đến hạn nhắn khách".
 *    Đơn khách đặt trên web thì người bấm Xác nhận thành người phụ trách.
 *  - deposit_percent / deposit_amount: tỷ lệ cọc (10–100%) và số tiền cọc
 *  - remind_on: hạn nhắn khách đóng phần còn lại (mặc định ngày đi − 7 ngày)
 *  - reminded_at / reminded_by: đã nhắn lúc nào, ai nhắn
 *  - remind_notified_at: đã báo chuông chưa (báo một lần, khỏi lặp mỗi ngày)
 *
 * Thêm ô cấu hình "Thông tin chuyển khoản" để in vào tin nhắn và phiếu PDF.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('deposit_percent', 5, 2)->nullable();
            $table->unsignedBigInteger('deposit_amount')->nullable();
            $table->date('remind_on')->nullable()->index();
            $table->timestamp('reminded_at')->nullable();
            $table->foreignId('reminded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('remind_notified_at')->nullable();
        });

        // Đơn đang chạy có ngày đi sắp tới → điền sẵn hạn nhắn = ngày đi − 7
        $dsDon = DB::table('bookings')
            ->join('tour_departures', 'tour_departures.id', '=', 'bookings.tour_departure_id')
            ->whereIn('bookings.status', ['pending', 'confirmed'])
            ->whereDate('tour_departures.start_date', '>=', now()->toDateString())
            ->select('bookings.id', 'tour_departures.start_date')
            ->get();
        foreach ($dsDon as $don) {
            DB::table('bookings')->where('id', $don->id)
                ->update(['remind_on' => Carbon::parse($don->start_date)->subDays(7)->toDateString()]);
        }

        if (Schema::hasTable('settings') && ! DB::table('settings')->where('key', 'bank_transfer')->exists()) {
            DB::table('settings')->insert([
                'key' => 'bank_transfer',
                'label' => 'Thông tin chuyển khoản (in vào tin nhắn nhắc tiền và phiếu xác nhận đặt tour)',
                'group' => 'general',
                'type' => 'textarea',
                'value' => null,
                'sort_order' => 99,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('reminded_by');
            $table->dropColumn(['deposit_percent', 'deposit_amount', 'remind_on', 'reminded_at', 'remind_notified_at']);
        });
        DB::table('settings')->where('key', 'bank_transfer')->delete();
    }
};
