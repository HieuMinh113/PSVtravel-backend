<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Xoá sạch dữ liệu mẫu (do DemoSeeder tạo) để bàn giao website chạy thật.
 *
 * CỐ Ý KHÔNG đụng tới: users, roles, permissions, settings, pages.
 * Đó là tài khoản quản trị, phân quyền và cấu hình — xoá là mất đường vào admin.
 */
class DonDuLieuMau extends Command
{
    protected $signature = 'psv:don-du-lieu-mau
                            {--don-hang : Xoá luôn đơn đặt tour, thanh toán và tin nhắn liên hệ}
                            {--force : Không hỏi xác nhận (dùng cho script tự động)}';

    protected $description = 'Xoá dữ liệu mẫu (tour, banner, review, cẩm nang...) để bắt đầu nhập dữ liệu thật';

    // Thứ tự quan trọng: bảng con xoá trước bảng cha để không vướng khoá ngoại
    private const BANG_NOI_DUNG = [
        'tour_images',
        'tour_itineraries',
        'tour_departures',
        'reviews',
        'moments',
        'banners',
        'guides',
        'flight_deals',
        'airlines',
        'visa_providers',
        'visa_countries',
        'tours',
        'categories',
    ];

    // Tách riêng: đây là dữ liệu KINH DOANH do khách thật tạo ra,
    // xoá nhầm là mất lịch sử không lấy lại được
    private const BANG_DON_HANG = [
        'payments',
        'bookings',
        'contact_messages',
    ];

    public function handle(): int
    {
        $bang = self::BANG_NOI_DUNG;

        if ($this->option('don-hang')) {
            $bang = array_merge(self::BANG_DON_HANG, $bang);
        }

        $this->newLine();
        $this->warn('Sắp xoá TOÀN BỘ dữ liệu trong các bảng sau:');
        $this->line('  '.implode(', ', $bang));
        $this->newLine();
        $this->info('Giữ nguyên: tài khoản, phân quyền, cài đặt, trang tĩnh.');

        if (! $this->option('don-hang')) {
            $this->line('Đơn đặt tour, thanh toán và tin nhắn liên hệ được GIỮ LẠI. Muốn xoá luôn thì thêm --don-hang');
        }

        $this->newLine();

        if (! $this->option('force')) {
            // Chạy qua `docker compose exec` trên Windows thường không có TTY thật,
            // câu hỏi xác nhận hiện ra nhưng gõ gì cũng bị đọc thành "no".
            // Bắt trường hợp đó và chỉ ra cách chạy đúng, thay vì im lặng huỷ.
            if (! $this->input->isInteractive() || ! stream_isatty(STDIN)) {
                $this->newLine();
                $this->error('Terminal này không nhận được câu trả lời xác nhận.');
                $this->line('Chạy lại kèm cờ --force để xoá thẳng:');
                $this->newLine();
                $this->line('  php artisan psv:don-du-lieu-mau --force');
                $this->line('  php artisan psv:don-du-lieu-mau --don-hang --force   (xoá luôn đơn đặt tour)');
                $this->newLine();

                return self::FAILURE;
            }

            if (! $this->confirm('Xác nhận xoá? Thao tác này KHÔNG hoàn tác được.')) {
                $this->line('Đã huỷ, không có gì bị xoá.');

                return self::SUCCESS;
            }
        }

        $tong = 0;

        DB::transaction(function () use ($bang, &$tong) {
            foreach ($bang as $ten) {
                if (! Schema::hasTable($ten)) {
                    $this->line("  bỏ qua {$ten} (chưa có bảng)");

                    continue;
                }

                $so = DB::table($ten)->count();
                // delete() thay vì truncate() để chạy được trong transaction
                // và không vướng ràng buộc khoá ngoại trên Postgres
                DB::table($ten)->delete();
                $tong += $so;

                $this->line("  đã xoá {$so} dòng từ {$ten}");
            }
        });

        // Đưa bộ đếm ID về 1 để dữ liệu thật bắt đầu từ số đẹp
        if (DB::getDriverName() === 'pgsql') {
            foreach ($bang as $ten) {
                if (Schema::hasTable($ten)) {
                    DB::statement("ALTER SEQUENCE IF EXISTS {$ten}_id_seq RESTART WITH 1");
                }
            }
        }

        $this->newLine();
        $this->info("Xong. Đã xoá {$tong} dòng.");
        $this->line('Ảnh cũ vẫn nằm trong storage/app/public — xoá tay nếu muốn dọn sạch ổ đĩa.');

        return self::SUCCESS;
    }
}
