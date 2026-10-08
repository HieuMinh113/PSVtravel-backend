<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\AttendanceFace;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\EventSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Booking\Models\Payment;
use Modules\Visa\Models\VisaCase;

/**
 * Xoá sạch dữ liệu mẫu (DemoSeeder, EventSeeder) để bàn giao website chạy thật.
 *
 * CỐ Ý KHÔNG đụng tới: mẫu checklist visa, tài khoản (trừ tài khoản mẫu khi có
 * --tai-khoan-mau), vai trò / phân quyền, cấu hình, trang tĩnh, sheet liên minh,
 * nhật ký hoạt động. Xoá những thứ đó là mất đường vào admin hoặc mất công nhập.
 *
 * Chạy --xem trước để thấy sẽ xoá bao nhiêu dòng mà chưa xoá gì.
 */
class DonDuLieuMau extends Command
{
    protected $signature = 'psv:don-du-lieu-mau
                            {--don-hang : Xoá luôn đơn đặt tour, thanh toán, hồ sơ visa, chấm công, tin nhắn liên hệ, thông báo}
                            {--tai-khoan-mau : Xoá luôn các tài khoản mẫu (visa1, visa2, dieuhanh, sale, ketoan, nhanvien, khach@example.com)}
                            {--xem : Chỉ liệt kê sẽ xoá gì, không xoá}
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
        'destinations',
        'tours',
        'categories',
    ];

    // Tách riêng: đây là dữ liệu KINH DOANH do khách / nhân viên thật tạo ra,
    // xoá nhầm là mất lịch sử không lấy lại được
    private const BANG_DON_HANG = [
        'visa_cases',
        'payments',
        'bookings',
        'contact_messages',
        'attendances',
        'attendance_faces',
        'notifications',
    ];

    /** Tài khoản do RoleSeeder / DemoSeeder tạo với mật khẩu ai cũng biết. */
    public const TAI_KHOAN_MAU = [
        'nhanvien@psvtravel.com',
        'visa1@psvtravel.com',
        'visa2@psvtravel.com',
        'dieuhanh@psvtravel.com',
        'sale@psvtravel.com',
        'ketoan@psvtravel.com',
        'khach@example.com',
    ];

    public function handle(): int
    {
        $bang = self::BANG_NOI_DUNG;
        if ($this->option('don-hang')) {
            $bang = array_merge(self::BANG_DON_HANG, $bang);
        }
        $bang = array_values(array_filter($bang, fn ($t) => Schema::hasTable($t)));
        $suKienMau = array_column(EventSeeder::mau(), 'slug');
        $taiKhoan = $this->option('tai-khoan-mau')
            ? User::whereIn('email', self::TAI_KHOAN_MAU)->orderBy('email')->get(['id', 'name', 'email'])
            : collect();

        $this->newLine();
        $this->warn('Sắp xoá TOÀN BỘ dữ liệu trong các bảng sau:');
        foreach ($bang as $t) {
            $this->line(sprintf('  %-20s %6d dòng', $t, DB::table($t)->count()));
        }
        $soSuKien = Schema::hasTable('events') ? Event::whereIn('slug', $suKienMau)->count() : 0;
        $this->line(sprintf('  %-20s %6d gói (chỉ các gói mẫu của EventSeeder)', 'events', $soSuKien));
        if ($this->option('tai-khoan-mau')) {
            $this->line('  Tài khoản mẫu: '.($taiKhoan->isEmpty() ? 'không có' : $taiKhoan->map(fn ($u) => "{$u->email} ({$u->name})")->implode(', ')));
        }
        $this->newLine();
        $this->info('Giữ nguyên: mẫu checklist visa, tài khoản quản trị, phân quyền, cài đặt, trang tĩnh, sheet liên minh, nhật ký.');
        if (! $this->option('don-hang')) {
            $this->line('Đơn đặt tour, thanh toán, hồ sơ visa, chấm công và tin nhắn liên hệ được GIỮ LẠI. Muốn xoá luôn thì thêm --don-hang');
        }
        if (! $this->option('tai-khoan-mau')) {
            $this->line('Tài khoản mẫu được GIỮ LẠI. Muốn xoá luôn thì thêm --tai-khoan-mau');
        }
        $this->newLine();

        if ($this->option('xem')) {
            $this->info('Chế độ xem (--xem): chưa xoá gì.');

            return self::SUCCESS;
        }

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
                $this->line('  php artisan psv:don-du-lieu-mau --don-hang --tai-khoan-mau --force   (xoá hết, chỉ giữ cấu hình + mẫu checklist)');
                $this->newLine();

                return self::FAILURE;
            }

            if (! $this->confirm('Xác nhận xoá? Thao tác này KHÔNG hoàn tác được.')) {
                $this->line('Đã huỷ, không có gì bị xoá.');

                return self::SUCCESS;
            }
        }

        $tong = 0;
        $tepXoa = [];

        DB::transaction(function () use ($bang, $suKienMau, $taiKhoan, &$tong, &$tepXoa) {
            foreach ($bang as $ten) {
                $so = DB::table($ten)->count();
                // Bảng có file trên ổ đĩa — gom đường dẫn để xoá file sau khi xoá dòng
                $tepXoa = [...$tepXoa, ...$this->tepCua($ten)];
                if ($ten === 'visa_cases') {
                    VisaCase::withTrashed()->get()->each->forceDelete(); // xoá luôn file giấy tờ
                }
                // delete() thay vì truncate() để chạy được trong transaction
                // và không vướng ràng buộc khoá ngoại trên Postgres
                DB::table($ten)->delete();
                $tong += $so;

                $this->line("  đã xoá {$so} dòng từ {$ten}");
            }

            if (Schema::hasTable('events')) {
                $so = Event::whereIn('slug', $suKienMau)->delete();
                $tong += $so;
                $this->line("  đã xoá {$so} gói sự kiện mẫu");
            }

            foreach ($taiKhoan as $u) {
                $tepXoa = [...$tepXoa, ...$this->tepCuaNguoi($u->id)];
                $u->tokens()->delete();
                $u->syncRoles([]);
                $u->syncPermissions([]);
                DB::table('notifications')->where('notifiable_type', User::class)->where('notifiable_id', $u->id)->delete();
                $u->delete();
                $this->line("  đã xoá tài khoản {$u->email}");
            }
        });

        foreach ($tepXoa as [$dia, $duong]) {
            Storage::disk($dia)->delete($duong);
        }

        // Đưa bộ đếm ID về 1 để dữ liệu thật bắt đầu từ số đẹp
        if (DB::getDriverName() === 'pgsql') {
            foreach ($bang as $ten) {
                if ($ten !== 'notifications') { // khoá chính là uuid, không có sequence
                    DB::statement("ALTER SEQUENCE IF EXISTS {$ten}_id_seq RESTART WITH 1");
                }
            }
        }

        $this->newLine();
        $this->info("Xong. Đã xoá {$tong} dòng".($taiKhoan->isNotEmpty() ? ' và '.$taiKhoan->count().' tài khoản mẫu' : '').'.');
        $this->line('Ảnh tour / banner đã tải lên vẫn nằm trong storage/app/public — xoá tay nếu muốn dọn sạch ổ đĩa.');

        return self::SUCCESS;
    }

    /** @return list<array{0: string, 1: string}> [ổ đĩa, đường dẫn] file gắn với các dòng của bảng */
    private function tepCua(string $bang): array
    {
        return match ($bang) {
            'payments' => Schema::hasColumn('payments', 'proof_images')
                ? Payment::whereNotNull('proof_images')->pluck('proof_images')->flatten()->filter()
                    ->map(fn ($d) => [Payment::DIA_CHUNG_TU, $d])->values()->all()
                : [],
            'attendances' => Attendance::query()->get(['in_photo', 'out_photo'])
                ->flatMap(fn ($a) => [$a->in_photo, $a->out_photo])->filter()
                ->map(fn ($d) => [Attendance::DIA, $d])->values()->all(),
            'attendance_faces' => AttendanceFace::whereNotNull('photo')->pluck('photo')
                ->map(fn ($d) => [Attendance::DIA, $d])->values()->all(),
            default => [],
        };
    }

    /** File chấm công / khuôn mặt của một tài khoản (khi chưa xoá cả bảng). */
    private function tepCuaNguoi(int $id): array
    {
        return [
            ...Attendance::where('user_id', $id)->get(['in_photo', 'out_photo'])
                ->flatMap(fn ($a) => [$a->in_photo, $a->out_photo])->filter()
                ->map(fn ($d) => [Attendance::DIA, $d])->values()->all(),
            ...AttendanceFace::where('user_id', $id)->whereNotNull('photo')->pluck('photo')
                ->map(fn ($d) => [Attendance::DIA, $d])->values()->all(),
        ];
    }
}
