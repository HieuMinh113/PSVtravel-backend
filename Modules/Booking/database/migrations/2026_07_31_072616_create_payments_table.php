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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('gateway')->default('momo');    // momo | vnpay | cash...
            $table->unsignedBigInteger('amount');
            $table->string('transaction_ref')->unique();   // mã mình sinh, gửi sang cổng
            $table->string('gateway_txn_id')->nullable();  // mã cổng trả về
            $table->string('status')->default('pending');  // pending | success | failed
            $table->json('gateway_response')->nullable();  // lưu phản hồi thô để đối soát
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
