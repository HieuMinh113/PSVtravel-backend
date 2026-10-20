<?php

namespace App\Filament\Resources\VisaDeadlines\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Modules\Tour\Services\HanNopVisa;
use Modules\Visa\Models\VisaDeadline;

class VisaDeadlineForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('country')
                    ->label('Quốc gia')
                    ->placeholder('VD: Trung Quốc, Hàn Quốc, Châu Âu (Schengen)')
                    ->helperText(fn (Get $get): string => self::tourKhop((string) $get('country')))
                    ->live(onBlur: true)
                    ->required()
                    ->maxLength(100)
                    // trùng cả khi khác dấu / chữ hoa ("trung quoc" = "Trung Quốc")
                    ->rule(fn (?VisaDeadline $record) => function (string $attribute, $value, \Closure $fail) use ($record) {
                        if ($c = HanNopVisa::trungTen((string) $value, $record?->getKey())) {
                            $fail("Nước này đã có trong danh sách (\"{$c}\").");
                        }
                    }),
                TextInput::make('days')
                    ->label('Số ngày nộp visa')
                    ->prefix('Trước ngày bay')
                    ->suffix('ngày')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(120)
                    ->required()
                    ->live(onBlur: true)
                    ->helperText(fn (Get $get): string => (int) $get('days') > 0
                        ? 'Ví dụ: bay ngày '.Carbon::create(2026, 11, 22)->format('d/m').' → từ ngày '
                            .Carbon::create(2026, 11, 22)->subDays((int) $get('days'))->format('d/m').' ẩn trên website (khách nộp hồ sơ, đặt tour chậm nhất ngày '
                            .Carbon::create(2026, 11, 22)->subDays((int) $get('days') + 1)->format('d/m').').'
                        : 'Bay ngày 22, nhập 7 → từ ngày 15 ngày khởi hành đó ẩn trên website.'),
                TextInput::make('note')
                    ->label('Ghi chú')
                    ->maxLength(500)
                    ->columnSpanFull(),
            ]);
    }

    /** "Áp dụng cho 5 tour: …" — tour nước ngoài có ô Quốc gia khớp tên nước này. */
    private static function tourKhop(string $nuoc): string
    {
        if (trim($nuoc) === '') {
            return 'Ghi đúng tên nước như ô "Quốc gia" của tour (không phân biệt dấu, chữ hoa). Tour nhiều nước ("Pháp - Thụy Sĩ - Ý") lấy số ngày lớn nhất.';
        }
        $ds = HanNopVisa::tourTheoNuoc($nuoc);

        return $ds ? 'Áp dụng cho '.count($ds).' tour: '.implode(', ', array_slice($ds, 0, 5)).(count($ds) > 5 ? '…' : '')
            : 'Chưa có tour nước ngoài nào ghi quốc gia này (kiểm tra ô "Quốc gia" của tour).';
    }
}
