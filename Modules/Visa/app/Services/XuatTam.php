<?php

namespace Modules\Visa\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Chỗ cất tạm file ZIP vừa xuất (ổ riêng tư) + link tải có chữ ký 10 phút.
 * File chưa ai tải thì lịch chạy hằng ngày dọn (xem VisaServiceProvider).
 */
class XuatTam
{
    public const THU_MUC = 'xuat-tam';

    public static function duong(string $ma): string
    {
        return self::THU_MUC.'/'.$ma.'.zip';
    }

    /** Chuyển file ZIP tạm vào ổ riêng, trả link tải. */
    public static function cat(string $fileTam, string $tenTai): string
    {
        $ma = (string) Str::uuid();
        Storage::disk('rieng')->makeDirectory(self::THU_MUC);
        rename($fileTam, Storage::disk('rieng')->path(self::duong($ma)));

        return URL::temporarySignedRoute('visa.tai-zip', now()->addMinutes(10), [
            'ma' => $ma,
            'u' => auth()->id(),
            'ten' => $tenTai,
        ]);
    }

    /** Xoá file tạm cũ hơn 1 ngày. */
    public static function donDep(): int
    {
        $dia = Storage::disk('rieng');
        $xoa = 0;
        clearstatcache();
        foreach ($dia->files(self::THU_MUC) as $f) {
            if ($dia->lastModified($f) < now()->subDay()->getTimestamp()) {
                $dia->delete($f);
                $xoa++;
            }
        }

        return $xoa;
    }
}
