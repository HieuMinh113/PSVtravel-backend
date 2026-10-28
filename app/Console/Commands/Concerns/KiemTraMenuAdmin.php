<?php

namespace App\Console\Commands\Concerns;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;

/**
 * Dùng chung cho các lệnh "vì sao không thấy mục … trong admin"
 * (psv:kiem-tra-maldives, psv:kiem-tra-voucher).
 */
trait KiemTraMenuAdmin
{
    /**
     * Trình duyệt (không phải dòng lệnh) dùng danh sách trang admin lưu tạm bởi
     * "optimize" / "filament:optimize": lưu từ trước khi có mục mới thì menu
     * không có mục đó dù mã đã đủ — lệnh chạy ở dòng lệnh không thấy được điều
     * này, nên đọc thẳng file. Xong xoá mọi thứ lưu tạm (optimize:clear).
     *
     * @return bool|null true = danh sách cũ (đây là lý do), false = đã có, null = không có file lưu tạm
     */
    protected function lamMoiMenuLuuTam(string $resource): ?bool
    {
        $tepMenu = Filament::getPanel('admin')->getComponentCachePath();
        $menuCu = is_file($tepMenu)
            ? ! in_array($resource, (array) ((include $tepMenu)['resources'] ?? []), true)
            : null;
        Artisan::call('optimize:clear'); // xoá danh sách trang lưu tạm, cấu hình / đường dẫn lưu tạm, bộ nhớ đệm

        return $menuCu;
    }

    protected function dat(string $s): void
    {
        $this->line("<fg=green>✓</> {$s}");
    }

    protected function hong(string $loi, string $cach): int
    {
        $this->line("<fg=red>✗</> {$loi}");
        $this->line("   → {$cach}");

        return self::FAILURE;
    }
}
