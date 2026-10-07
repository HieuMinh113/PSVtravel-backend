<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ô cấu hình "Ảnh đầu phiếu xác nhận đặt tour": băng rôn công ty in ở đầu
 * phiếu PDF. Để trống = dùng ảnh mặc định resources/images/phieu-xac-nhan-header.jpg.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('settings') && ! DB::table('settings')->where('key', 'pdf_header_image')->exists()) {
            DB::table('settings')->insert([
                'key' => 'pdf_header_image',
                'label' => 'Ảnh đầu phiếu xác nhận đặt tour (để trống = ảnh mặc định)',
                'group' => 'general',
                'type' => 'image',
                'value' => null,
                'sort_order' => 98,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'pdf_header_image')->delete();
    }
};
