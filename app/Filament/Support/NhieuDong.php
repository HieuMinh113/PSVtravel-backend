<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Textarea;
use Modules\Tour\Models\Tour;

/**
 * Ô nhập "mỗi dòng một mục" lưu thành mảng (giá bao gồm, tiện ích, điểm nổi
 * bật…). Dán thẳng từ file chương trình cũng được: dấu ➢ • ▪ tự tách dòng.
 */
class NhieuDong
{
    public static function o(string $ten, string $nhan, int $dong = 5): Textarea
    {
        return Textarea::make($ten)
            ->label($nhan)
            ->rows($dong)
            ->formatStateUsing(fn ($state) => is_array($state) ? implode("\n", $state) : (string) ($state ?? ''))
            ->dehydrateStateUsing(fn ($state) => Tour::tachTungMuc($state));
    }
}
