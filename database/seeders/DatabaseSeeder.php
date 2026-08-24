<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            // Tạo sẵn các ô cài đặt và trang hệ thống (dùng firstOrCreate nên
            // chạy lại nhiều lần không sao). Thiếu bước này thì trang Cấu hình
            // trong admin trống trơn, không có ô nào để nhập.
            \Modules\Page\Database\Seeders\PageDatabaseSeeder::class,
            // Ghi thông tin pháp lý thật theo giấy đăng ký doanh nghiệp
            ThongTinPhapLySeeder::class,
        ]);
    }
}