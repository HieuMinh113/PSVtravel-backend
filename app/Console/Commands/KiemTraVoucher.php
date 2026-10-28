<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\KiemTraMenuAdmin;
use App\Filament\Pages\QuetVoucher;
use App\Filament\Resources\VoucherBatches\VoucherBatchResource;
use App\Models\User;
use App\Models\VoucherBatch;
use Database\Seeders\QuyenVoucherSeeder;
use Filament\Facades\Filament;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Kiểm tra vì sao không thấy mục Voucher trong admin: mã nguồn, bảng CSDL, quyền
 * theo vai trò, danh sách trang / đường dẫn lưu tạm, tài khoản nào thấy menu.
 * Tự sửa phần an toàn (thêm quyền voucher cho các vai trò chuẩn, xoá lưu tạm),
 * phần còn lại in rõ cần làm gì.
 *
 *   docker compose exec app php artisan psv:kiem-tra-voucher
 */
class KiemTraVoucher extends Command
{
    use KiemTraMenuAdmin;

    protected $signature = 'psv:kiem-tra-voucher';

    protected $description = 'Kiểm tra (và sửa phần an toàn) vì sao không thấy mục Voucher trong admin';

    public function handle(): int
    {
        // 1. Mã nguồn (lệnh này chạy được tức là đã có mã voucher — kiểm tra cho đủ)
        if (! class_exists(VoucherBatchResource::class) || ! class_exists(QuetVoucher::class)) {
            return $this->hong('Chưa có mã Voucher.', 'Kéo mã mới: git pull origin main, rồi chạy lại lệnh này.');
        }
        $this->dat('Mã nguồn Voucher đã có.');

        // 2. Bảng CSDL
        $thieu = array_values(array_filter(['voucher_batches', 'vouchers'], fn ($b) => ! Schema::hasTable($b)));
        if ($thieu) {
            return $this->hong('Thiếu bảng: '.implode(', ', $thieu).'.', 'Chạy: docker compose exec app php artisan migrate');
        }
        $this->dat('Bảng dữ liệu voucher đủ ('.DB::table('voucher_batches')->count().' đợt, '.DB::table('vouchers')->count().' mã).');

        // 3. Quyền: thêm cho các vai trò chuẩn (chỉ thêm, chạy lại được), xoá bộ nhớ đệm quyền
        $truoc = Permission::query()->where('name', 'like', '%:Voucher%')->count();
        (new QuyenVoucherSeeder)->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $sau = Permission::query()->where('name', 'like', '%:Voucher%')->count();
        $this->dat("Quyền voucher: {$sau} quyền".($sau > $truoc ? ' (vừa thêm '.($sau - $truoc).')' : '').'.');
        $vaiTro = Role::query()->where('guard_name', 'web')->get()
            ->map(fn (Role $r) => $r->name.($r->hasPermissionTo('ViewAny:VoucherBatch') || $r->hasPermissionTo(QuyenVoucherSeeder::QUET) ? ' ✓' : ' ✗'));
        $this->line('   Vai trò (✓ = thấy mục Voucher): '.$vaiTro->implode(', '));

        // 4. Danh sách trang admin + đường dẫn lưu tạm (sau "php artisan optimize")
        $duongDanCu = app()->routesAreCached() && ! Route::has('voucher.tai');
        $menuCu = $this->lamMoiMenuLuuTam(VoucherBatchResource::class);
        if ($menuCu || $duongDanCu) {
            $this->line('<fg=yellow>!</> Admin đang dùng danh sách trang / đường dẫn lưu tạm CŨ (chưa có Voucher) — ĐÂY LÀ LÝ DO không thấy menu. Đã xoá.');
        } elseif ($menuCu === false) {
            $this->dat('Danh sách trang admin lưu tạm đã có Voucher (đã làm mới).');
        }
        if (! in_array(VoucherBatchResource::class, Filament::getPanel('admin')->getResources(), true)) {
            return $this->hong('Admin chưa nhận trang Đợt voucher.', 'Chạy: docker compose restart app rồi chạy lại lệnh này.');
        }
        $this->dat('Mã admin có đủ các trang Voucher.');

        // 5. Tài khoản nhân viên nào thấy menu
        $nv = User::query()->whereHas('roles', fn ($q) => $q->where('name', '!=', 'customer'))->with('roles')->orderBy('id')->limit(50)->get();
        $thay = $nv->filter(fn (User $u) => $this->thayMenu($u));
        $ten = fn (User $u) => $u->email.' ('.$u->roles->pluck('name')->implode(', ').')';
        $this->line('   Tài khoản thấy mục Voucher: '.($thay->map($ten)->implode('; ') ?: 'không có'));
        $khong = $nv->diff($thay);
        if ($khong->isNotEmpty()) {
            $this->line('   Tài khoản KHÔNG thấy: '.$khong->map($ten)->implode('; '));
            $this->line('   → Muốn tài khoản nào thấy: Hệ thống → Vai trò → mở vai trò của tài khoản đó → tích "Quét voucher" (Scan:Voucher) và quyền Đợt voucher / Mã voucher.');
        }

        $this->newLine();
        $this->info($thay->isNotEmpty()
            ? 'Xong. Chạy tiếp: docker compose restart app — rồi đăng xuất admin, đăng nhập lại bằng một tài khoản có ✓ ở trên, bấm Ctrl + F5. Nhóm "Voucher" nằm giữa "Maldives" và "Visa".'
            : 'Chưa có tài khoản nào có quyền voucher — gán vai trò admin / super_admin cho tài khoản của bạn.');

        return self::SUCCESS;
    }

    private function thayMenu(User $u): bool
    {
        try {
            return $u->can('viewAny', VoucherBatch::class) || $u->can(QuyenVoucherSeeder::QUET);
        } catch (Throwable) {
            return false;
        }
    }
}
