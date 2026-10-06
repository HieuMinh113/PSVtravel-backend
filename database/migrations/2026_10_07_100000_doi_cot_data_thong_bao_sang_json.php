<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Đổi cột notifications.data từ text sang json trên PostgreSQL.
 *
 * Bản đầu của bảng notifications tạo cột này kiểu text (mặc định của
 * Laravel). Chuông thông báo Filament lọc bằng data->>'format', mà
 * PostgreSQL không có toán tử ->> cho text → mọi trang quản trị lỗi 500
 * ("operator does not exist: text ->> unknown"). Máy đã chạy bản cũ cần
 * migration này; máy mới cài thì cột đã là json, lệnh dưới không làm gì.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return; // SQLite / MySQL so được ->> trên text, không cần đổi
        }

        $kieu = DB::scalar(
            "select data_type from information_schema.columns
             where table_schema = current_schema() and table_name = 'notifications' and column_name = 'data'"
        );

        if ($kieu === 'text') {
            DB::statement('alter table notifications alter column data type json using data::json');
        }
    }

    public function down(): void
    {
        // Không đổi ngược về text: đổi ngược là làm hỏng lại chuông thông báo.
    }
};
