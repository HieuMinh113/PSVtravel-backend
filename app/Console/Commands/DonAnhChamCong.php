<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Ảnh chụp lúc chấm công giữ 3 tháng rồi xoá (dữ liệu khuôn mặt — NĐ 13/2023).
 * Giờ chấm, vị trí, lý do vẫn giữ. Ảnh đăng ký khuôn mặt không xoá ở đây.
 */
class DonAnhChamCong extends Command
{
    protected $signature = 'cham-cong:don-anh {--thang=3 : Giữ ảnh bao nhiêu tháng}';

    protected $description = 'Xoá ảnh chấm công cũ hơn 3 tháng';

    public function handle(): int
    {
        $moc = today()->subMonths(max(1, (int) $this->option('thang')));
        $so = 0;
        Attendance::where('work_date', '<', $moc)
            ->where(fn ($q) => $q->whereNotNull('in_photo')->orWhereNotNull('out_photo'))
            ->chunkById(200, function ($ds) use (&$so) {
                foreach ($ds as $a) {
                    Storage::disk(Attendance::DIA)->delete(array_filter([$a->in_photo, $a->out_photo]));
                    $a->forceFill(['in_photo' => null, 'out_photo' => null])->saveQuietly();
                    $so++;
                }
            });
        $this->info("Đã xoá ảnh của {$so} ngày chấm công.");

        return self::SUCCESS;
    }
}
