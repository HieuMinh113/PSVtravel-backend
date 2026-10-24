<?php

namespace App\Console\Commands;

use App\Services\ThuNhoAnh;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Thu nhỏ ảnh đã chèn vào nội dung bài từ trước (ảnh chèn mới tự thu nhỏ khi
 * lưu bài). Bản gốc cất ở storage/app/private/anh-goc/ — kiểm tra web ổn thì
 * xoá thư mục đó để lấy lại dung lượng.
 *
 *   docker compose -f docker-compose.prod.yml exec -T app php artisan psv:thu-nho-anh --xem
 *   docker compose -f docker-compose.prod.yml exec -T app php artisan psv:thu-nho-anh
 */
class ThuNhoAnhCu extends Command
{
    protected $signature = 'psv:thu-nho-anh
        {--xem : Chỉ tính thử, không ghi gì}
        {--thu-muc=* : Thư mục trên ổ public (mặc định: mọi thư mục ảnh nội dung */noi-dung)}';

    protected $description = 'Thu nhỏ ảnh trong nội dung bài: rộng tối đa 2000px, JPEG nặng thì nén lại (giữ bản gốc)';

    public function handle(): int
    {
        $o = Storage::disk('public');
        $thuMuc = $this->option('thu-muc') ?: array_values(array_filter($o->directories(), fn ($d) => $o->directoryExists($d.'/noi-dung')));
        $thuMuc = array_map(fn ($d) => str_ends_with($d, '/noi-dung') ? $d : $d.'/noi-dung', $thuMuc);
        $xem = (bool) $this->option('xem');

        $dong = [];
        $truoc = $sau = 0;
        foreach ($thuMuc as $d) {
            foreach ($o->files($d) as $p) {
                if (! preg_match('/\.(jpe?g|png|webp)$/i', $p)) {
                    continue;
                }
                $kq = ThuNhoAnh::xuLy($o->path($p), ! $xem, Storage::disk('local')->path('anh-goc/'.$p));
                if ($kq) {
                    $truoc += $kq['truoc'];
                    $sau += $kq['sau'];
                    $dong[] = [$p, self::mb($kq['truoc']).' → '.self::mb($kq['sau']), $kq['rong_truoc'].' → '.$kq['rong_sau'].'px'];
                }
            }
        }

        if (! $dong) {
            $this->info($thuMuc ? 'Không có ảnh nào cần thu nhỏ trong: '.implode(', ', $thuMuc) : 'Chưa có thư mục ảnh nội dung nào (*/noi-dung).');

            return self::SUCCESS;
        }
        $this->table(['Ảnh', 'Dung lượng', 'Chiều rộng'], $dong);
        $this->info(sprintf('%s %d ảnh: %s → %s (bớt %s).', $xem ? 'Sẽ thu nhỏ' : 'Đã thu nhỏ', count($dong), self::mb($truoc), self::mb($sau), self::mb($truoc - $sau)));
        if ($xem) {
            $this->line('Chạy lại không có --xem để ghi. Bản gốc sẽ cất ở storage/app/private/anh-goc/.');
        } else {
            $this->line('Bản gốc ở storage/app/private/anh-goc/ — web hiển thị ổn thì xoá thư mục đó để lấy lại dung lượng.');
        }

        return self::SUCCESS;
    }

    private static function mb(int $b): string
    {
        return number_format($b / 1_048_576, 1, ',', '.').' MB';
    }
}
