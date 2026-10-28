<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Voucher giấy / ảnh: mỗi đợt (mệnh giá, hạn dùng, ảnh 2 mặt do bạn thiết kế)
 * sinh ra 1–1000 mã, mỗi mã dùng MỘT lần. Khách quét QR trên voucher để xem
 * còn dùng được không; nhân viên có quyền quét thì đánh dấu đã dùng. Hệ thống
 * không trừ tiền — chỉ kiểm tra và ghi lại.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_batches', function (Blueprint $t) {
            $t->id();
            $t->string('name');                               // Voucher Ốc & Du lịch 200k
            $t->string('code_prefix', 5)->default('PSV');     // PSV-7KQ3-MZ8T
            $t->unsignedInteger('value');                     // mệnh giá (đồng)
            $t->date('expires_at');                           // dùng được hết ngày này
            $t->string('applies_to')->nullable();             // Dịch vụ ăn uống tại …
            $t->text('conditions')->nullable();               // Không quy đổi thành tiền mặt…
            $t->string('front_image')->nullable();            // ảnh mặt trước (161×106 mm có tràn lề, hoặc 155×100)
            $t->string('back_image')->nullable();
            $t->string('code_side', 10)->default('front');    // mặt in ô mã + QR: front | back
            $t->string('code_corner', 20)->default('bottom_right');
            $t->decimal('code_margin_mm', 4, 1)->default(7);  // cách mép thành phẩm
            $t->decimal('code_size_mm', 4, 1)->default(28);   // bề rộng ô mã
            $t->boolean('show_barcode')->default(false);      // in thêm mã vạch cho máy quét cầm tay
            $t->string('status', 20)->default('active');      // active | paused
            $t->text('note')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('vouchers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('batch_id')->constrained('voucher_batches')->cascadeOnDelete();
            $t->unsignedInteger('serial');                    // số thứ tự trong đợt: 1, 2, …
            $t->string('code', 24)->unique();                 // PSV-7KQ3-MZ8T (in trên voucher)
            $t->string('lookup_key', 24)->unique();           // PSV7KQ3MZ8T — tra cứu không phân biệt gạch / khoảng trắng
            $t->string('status', 20)->default('unused');      // unused | used | cancelled
            $t->string('issued_to')->nullable();              // đã phát cho ai (ghi chú)
            $t->timestamp('used_at')->nullable();
            $t->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('used_note', 500)->nullable();         // dùng cho đơn / bàn / dịch vụ nào
            $t->timestamp('cancelled_at')->nullable();
            $t->string('cancel_reason')->nullable();
            $t->timestamps();
            $t->unique(['batch_id', 'serial']);
            $t->index(['batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('voucher_batches');
    }
};
