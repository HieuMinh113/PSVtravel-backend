<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Booking\Database\Seeders\QuyenDonTourSeeder;

/**
 * Đơn đặt tour ghi rõ 3 người (để thống kê hoa hồng theo tháng):
 *  - created_by: nhân viên tạo đơn (trống = khách tự đặt trên web)
 *  - confirmed_by / confirmed_at: ai bấm xác nhận, lúc nào — tháng chốt đơn
 *  - assigned_to: người phụ trách, HƯỞNG HOA HỒNG. Mặc định = người tạo; đơn
 *    web = người xác nhận. Chỉ quản lý được đổi.
 *  - source: quan_tri | web
 *  - paid_total: tổng đã thu (khoản kế toán đã duyệt), tính sẵn cho thống kê
 *
 * Khoản thu: ảnh chứng minh chuyển khoản + kế toán duyệt (ai, lúc nào).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('source', 20)->default('quan_tri');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable()->index();
            $table->unsignedBigInteger('paid_total')->default(0);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->json('proof_images')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
        });

        // Đơn cũ: chưa có người tạo = khách đặt web. Người phụ trách = người
        // tạo / người đã xác nhận (trước đây ghi chung một cột).
        DB::table('bookings')->whereNull('created_by')->update(['source' => 'web']);
        DB::table('bookings')->update(['assigned_to' => DB::raw('created_by')]);
        // Không biết ngày xác nhận thật của đơn cũ → lấy ngày đặt
        DB::table('bookings')->whereIn('status', ['confirmed', 'completed'])
            ->update(['confirmed_at' => DB::raw('created_at'), 'confirmed_by' => DB::raw('created_by')]);

        DB::table('payments')->where('status', 'success')
            ->select('booking_id', DB::raw('SUM(amount) as tong'))->groupBy('booking_id')
            ->orderBy('booking_id')
            ->each(fn ($r) => DB::table('bookings')->where('id', $r->booking_id)->update(['paid_total' => (int) $r->tong]));

        (new QuyenDonTourSeeder)->run();
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['proof_images', 'approved_at']);
        });
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropIndex(['confirmed_at']);
            $table->dropColumn(['source', 'confirmed_at', 'paid_total']);
        });
    }
};
