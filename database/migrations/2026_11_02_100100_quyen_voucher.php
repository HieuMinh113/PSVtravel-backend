<?php

use Database\Seeders\QuyenVoucherSeeder;
use Illuminate\Database\Migrations\Migration;

/** Quyền voucher mặc định theo vai trò — máy chủ thật có ngay khi triển khai. */
return new class extends Migration
{
    public function up(): void
    {
        (new QuyenVoucherSeeder)->run();
    }

    public function down(): void
    {
        // Không thu hồi quyền đã cấp
    }
};
