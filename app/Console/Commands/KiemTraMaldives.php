<?php

namespace App\Console\Commands;

use App\Filament\Resources\MaldivesCombos\MaldivesComboResource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Maldives\Database\Seeders\QuyenMaldivesSeeder;
use Modules\Maldives\Models\MaldivesCombo;
use Nwidart\Modules\Facades\Module;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Kiểm tra vì sao không thấy mục Maldives trong admin: mã nguồn, module, bảng
 * CSDL, quyền theo vai trò, tài khoản nào thấy menu. Tự sửa phần an toàn
 * (thêm quyền Maldives cho các vai trò chuẩn, xoá bộ nhớ đệm quyền / menu),
 * phần còn lại in rõ cần làm gì.
 *
 *   docker compose exec app php artisan psv:kiem-tra-maldives
 */
class KiemTraMaldives extends Command
{
    protected $signature = 'psv:kiem-tra-maldives';

    protected $description = 'Kiểm tra (và sửa phần an toàn) vì sao không thấy mục Maldives trong admin';

    private const BANG = ['maldives_resorts', 'maldives_room_types', 'maldives_combos', 'maldives_combo_days', 'maldives_combo_dates', 'maldives_bookings'];

    public function handle(): int
    {
        // 1. Mã nguồn + module
        if (! class_exists(MaldivesCombo::class) || ! class_exists(MaldivesComboResource::class)) {
            return $this->hong('Chưa nạp được mã Maldives.', 'Chạy: docker compose exec app composer dump-autoload (module mới cần nạp lại danh sách lớp).');
        }
        $this->dat('Mã nguồn Maldives đã có.');
        $module = Module::find('Maldives');
        if (! $module?->isEnabled()) {
            return $this->hong('Module Maldives chưa bật.', 'Kiểm tra file modules_statuses.json có dòng "Maldives": true, rồi chạy lại lệnh này.');
        }
        $this->dat('Module Maldives đang bật.');

        // 2. Bảng CSDL
        $thieu = array_values(array_filter(self::BANG, fn ($b) => ! Schema::hasTable($b)));
        if ($thieu || ! Schema::hasColumn('maldives_room_types', 'adults_only')) {
            return $this->hong('Thiếu bảng / cột: '.implode(', ', $thieu ?: ['maldives_room_types.adults_only']).'.', 'Chạy: docker compose exec app php artisan migrate');
        }
        $this->dat('Bảng dữ liệu Maldives đủ ('.DB::table('maldives_combos')->count().' combo, '.DB::table('maldives_resorts')->count().' resort).');

        // 3. Quyền: thêm cho các vai trò chuẩn (chỉ thêm, chạy lại được), xoá bộ nhớ đệm quyền
        $truoc = Permission::query()->where('name', 'like', '%:Maldives%')->count();
        (new QuyenMaldivesSeeder)->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $sau = Permission::query()->where('name', 'like', '%:Maldives%')->count();
        $this->dat("Quyền Maldives: {$sau} quyền".($sau > $truoc ? ' (vừa thêm '.($sau - $truoc).')' : '').'.');
        $vaiTro = Role::query()->where('guard_name', 'web')->get()
            ->map(fn (Role $r) => $r->name.($r->hasPermissionTo('ViewAny:MaldivesCombo') || $r->hasPermissionTo('ViewAny:MaldivesBooking') ? ' ✓' : ' ✗'));
        $this->line('   Vai trò (✓ = thấy mục Maldives): '.$vaiTro->implode(', '));

        // 4. Menu admin. Trình duyệt (không phải dòng lệnh) dùng danh sách trang admin lưu tạm
        // bởi "optimize" / "filament:optimize": lưu từ trước khi có Maldives thì menu không có
        // Maldives dù mã đã đủ — lệnh chạy ở dòng lệnh không thấy được điều này, nên đọc thẳng file
        $tepMenu = Filament::getPanel('admin')->getComponentCachePath();
        $coTep = is_file($tepMenu);
        $menuCu = $coTep && ! in_array(MaldivesComboResource::class, (array) ((include $tepMenu)['resources'] ?? []), true);
        Artisan::call('optimize:clear'); // xoá danh sách trang lưu tạm, cấu hình / đường dẫn lưu tạm, bộ nhớ đệm
        if ($menuCu) {
            $this->line('<fg=yellow>!</> Admin đang dùng danh sách trang lưu tạm CŨ (chưa có Maldives) — ĐÂY LÀ LÝ DO không thấy menu. Đã xoá.');
        } elseif ($coTep) {
            $this->dat('Danh sách trang admin lưu tạm đã có Maldives (đã làm mới).');
        }
        if (! in_array(MaldivesComboResource::class, Filament::getPanel('admin')->getResources(), true)) {
            return $this->hong('Admin chưa nhận trang Combo Maldives.', 'Chạy: docker compose restart app rồi chạy lại lệnh này.');
        }
        $this->dat('Mã admin có đủ các trang Maldives.');

        // 5. Tài khoản nhân viên nào thấy menu
        $nv = User::query()->whereHas('roles', fn ($q) => $q->where('name', '!=', 'customer'))->with('roles')->orderBy('id')->limit(50)->get();
        $thay = $nv->filter(fn (User $u) => $this->thayMenu($u));
        $this->line('   Tài khoản thấy mục Maldives: '.($thay->map(fn (User $u) => $u->email.' ('.$u->roles->pluck('name')->implode(', ').')')->implode('; ') ?: 'không có'));
        $khong = $nv->diff($thay);
        if ($khong->isNotEmpty()) {
            $this->line('   Tài khoản KHÔNG thấy: '.$khong->map(fn (User $u) => $u->email.' ('.$u->roles->pluck('name')->implode(', ').')')->implode('; '));
            $this->line('   → Muốn tài khoản nào thấy: Hệ thống → Vai trò → mở vai trò của tài khoản đó → tích quyền Maldives Combo / Resort / Booking.');
        }

        $this->newLine();
        $this->info($thay->isNotEmpty()
            ? 'Xong. Chạy tiếp: docker compose restart app — rồi đăng xuất admin, đăng nhập lại bằng một tài khoản có ✓ ở trên, bấm Ctrl + F5. Mục "Maldives" nằm dưới "Tour & điều hành".'
            : 'Chưa có tài khoản nào có quyền Maldives — gán vai trò admin / super_admin cho tài khoản của bạn.');

        return self::SUCCESS;
    }

    private function thayMenu(User $u): bool
    {
        try {
            return $u->can('viewAny', MaldivesCombo::class) || $u->can('ViewAny:MaldivesBooking');
        } catch (Throwable) {
            return false;
        }
    }

    private function dat(string $s): void
    {
        $this->line("<fg=green>✓</> {$s}");
    }

    private function hong(string $loi, string $cach): int
    {
        $this->line("<fg=red>✗</> {$loi}");
        $this->line("   → {$cach}");

        return self::FAILURE;
    }
}
