<?php

namespace App\Filament\Resources\AllianceSources\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Modules\Alliance\Models\AllianceSource;
use Modules\Alliance\Services\TaiSheetLienMinh;

class AllianceSourceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Tên đối tác')
                    ->placeholder('VD: V1 Travel, Nhật Bản AZ, M Tour')
                    ->required()
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->label('Đang theo dõi')
                    ->helperText('Tắt thì máy ngừng đọc sheet này (dữ liệu cũ vẫn giữ tới lần bật lại).')
                    ->default(true)
                    ->inline(false),
                TextInput::make('sheet_url')
                    ->label('Link sheet')
                    ->placeholder('https://docs.google.com/spreadsheets/d/…')
                    ->helperText('Dán nguyên link đối tác gửi. Sheet phải để chế độ “Bất kỳ ai có đường liên kết đều xem được”.')
                    ->url()
                    ->required()
                    ->rules([
                        fn (): \Closure => function (string $attribute, $value, \Closure $fail) {
                            if ($value && ! TaiSheetLienMinh::maFile($value)) {
                                $fail('Đây không phải link Google Sheet / Google Drive.');
                            }
                        },
                    ])
                    ->columnSpanFull(),
                Textarea::make('contact')
                    ->label('Liên hệ đối tác')
                    ->placeholder('Tên điều hành, SĐT, Zalo, email…')
                    ->rows(2),
                Textarea::make('note')
                    ->label('Ghi chú nội bộ')
                    ->rows(2),
                Text::make(fn (?AllianceSource $record) => $record?->last_error
                    ? 'Lần đọc gần nhất bị lỗi: '.$record->last_error
                    : '')
                    ->color('danger')
                    ->visible(fn (?AllianceSource $record) => (bool) $record?->last_error)
                    ->columnSpanFull(),
                Text::make(fn (?AllianceSource $record) => $record?->warnings
                    ? 'Cảnh báo khi đọc: '.implode(' · ', $record->warnings)
                    : '')
                    ->color('warning')
                    ->visible(fn (?AllianceSource $record) => (bool) $record?->warnings)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }
}
