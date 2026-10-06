<?php

namespace Modules\Alliance\Console;

use Illuminate\Console\Command;
use Modules\Alliance\Models\AllianceSource;
use Modules\Alliance\Services\DongBoLienMinh;

/**
 * Đọc lại các sheet liên minh đã tới hạn. Lịch chạy tự gọi 5 phút một lần;
 * gọi tay để kiểm tra:
 *
 *   php artisan lien-minh:dong-bo --tat-ca          # đọc lại mọi sheet ngay
 *   php artisan lien-minh:dong-bo --nguon=3         # chỉ sheet số 3
 */
class DongBoLienMinhCommand extends Command
{
    protected $signature = 'lien-minh:dong-bo
        {--nguon=* : Chỉ đọc các nguồn có id này}
        {--tat-ca : Đọc ngay, không chờ tới hạn}';

    protected $description = 'Đọc lại sheet chỗ trống của đối tác liên minh';

    public function handle(DongBoLienMinh $dongBo): int
    {
        $q = AllianceSource::query()->where('is_active', true)->orderBy('id');
        if ($ids = $this->option('nguon')) {
            $q->whereIn('id', $ids);
        } elseif (! $this->option('tat-ca')) {
            $han = now()->subMinutes(max(1, (int) config('alliance.chu_ky_phut', 10)))->addSeconds(30);
            $q->where(fn ($w) => $w->whereNull('last_checked_at')->orWhere('last_checked_at', '<=', $han));
        }

        $loi = 0;
        foreach ($q->get() as $nguon) {
            $kq = $dongBo->dongBo($nguon);
            if ($kq['ok']) {
                $this->info("✓ {$nguon->name}: {$kq['so_ngay']} ngày đi, {$kq['moi']} mới, {$kq['bao_dong']} báo động, {$kq['cap_nhat_web']} ngày trên web đổi số chỗ");
            } else {
                $loi++;
                $this->error("✗ {$nguon->name}: {$kq['loi']}");
            }
        }

        return $loi ? self::FAILURE : self::SUCCESS;
    }
}
