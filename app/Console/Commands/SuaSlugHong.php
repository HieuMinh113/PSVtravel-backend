<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Modules\Guide\Models\Guide;
use Modules\Tour\Models\Tour;

/**
 * Sửa đường dẫn (slug) sai định dạng của bài cẩm nang / tour đã lưu từ trước
 * khi form kiểm tra (VD bài lưu nguyên tiêu đề "Tempura Nhật Bản: Nguồn Gốc…").
 * Bài / tour đang hiện trên web tự ghi chuyển hướng 301 từ link cũ sang link mới.
 *
 *   docker compose -f docker-compose.prod.yml exec -T app php artisan psv:sua-slug --xem
 *   docker compose -f docker-compose.prod.yml exec -T app php artisan psv:sua-slug
 */
class SuaSlugHong extends Command
{
    protected $signature = 'psv:sua-slug {--xem : Chỉ liệt kê, không lưu}';

    protected $description = 'Sửa đường dẫn sai định dạng của bài cẩm nang / tour (tự chuyển hướng link cũ)';

    private const DUNG = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public function handle(): int
    {
        $xem = (bool) $this->option('xem');
        $dong = [];
        foreach ([[Guide::class, 'Cẩm nang', 'title'], [Tour::class, 'Tour', 'name']] as [$lop, $nhan, $ten]) {
            foreach ($lop::withTrashed()->get(['id', 'slug', $ten, 'status', ...($lop === Tour::class ? ['type'] : [])]) as $m) {
                if (preg_match(self::DUNG, (string) $m->slug)) {
                    continue;
                }
                $goc = Str::slug((string) $m->slug) ?: Str::slug((string) $m->{$ten}) ?: $nhan.'-'.$m->id;
                $moi = $goc;
                for ($i = 2; $lop::withTrashed()->where('slug', $moi)->whereKeyNot($m->id)->exists(); $i++) {
                    $moi = "{$goc}-{$i}";
                }
                $dong[] = [$nhan, $m->slug, $moi, $m->status];
                if (! $xem) {
                    // Lưu qua model (không saveQuietly): bài đang hiện → tự ghi 301
                    $lop::withTrashed()->find($m->id)->update(['slug' => $moi]);
                }
            }
        }

        if (! $dong) {
            $this->info('Không có đường dẫn nào sai định dạng.');

            return self::SUCCESS;
        }
        $this->table(['Loại', 'Đường dẫn cũ', 'Đường dẫn mới', 'Trạng thái'], $dong);
        $this->info(($xem ? 'Sẽ sửa ' : 'Đã sửa ').count($dong).' đường dẫn.'.($xem ? ' Chạy lại không có --xem để lưu.' : ' Link cũ của bài đang hiện đã tự chuyển 301 sang link mới.'));

        return self::SUCCESS;
    }
}
