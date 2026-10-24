<?php

namespace App\Jobs;

use App\Services\ThuNhoAnh;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;

/**
 * Thu nhỏ ảnh vừa chèn vào nội dung bài (xem ThuNhoAnh). Chạy trong hàng đợi:
 * mỗi ảnh 6 MB mất 1–2 giây, chạy ngay lúc bấm Lưu thì nhân viên phải chờ.
 * Bản gốc cất ở storage/app/private/anh-goc/ (không công khai).
 */
class ThuNhoAnhNoiDung implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** @param list<string> $anh đường dẫn trên ổ public, VD guides/noi-dung/abc.jpg */
    public function __construct(public array $anh) {}

    public function handle(): void
    {
        $o = Storage::disk('public');
        foreach ($this->anh as $p) {
            if (str_contains($p, '..') || ! $o->exists($p)) {
                continue;
            }
            ThuNhoAnh::xuLy($o->path($p), true, Storage::disk('local')->path('anh-goc/'.$p));
        }
    }
}
