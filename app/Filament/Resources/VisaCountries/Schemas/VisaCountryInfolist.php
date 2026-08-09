<?php

namespace App\Filament\Resources\VisaCountries\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Modules\Visa\Models\VisaCountry;

class VisaCountryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ImageEntry::make('flag_image')->label('Ảnh cờ')->placeholder('—'),
                TextEntry::make('name')->label('Quốc gia'),
                TextEntry::make('slug')->label('Đường dẫn'),
                TextEntry::make('visa_type')
                    ->label('Loại visa')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'business' => 'Công tác',
                        'study' => 'Du học',
                        default => 'Du lịch',
                    }),
                TextEntry::make('price')->label('Chi phí dịch vụ')->money('VND')->weight('bold'),
                TextEntry::make('processing_time')->label('Thời gian xử lý')->placeholder('—'),
                TextEntry::make('success_rate')
                    ->label('Tỷ lệ đậu')
                    ->formatStateUsing(fn (?int $state): string => $state ? $state.'%' : '—'),
                TextEntry::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'published' ? 'Đang hiển thị' : 'Đã ẩn')
                    ->color(fn (string $state): string => $state === 'published' ? 'success' : 'gray'),
                TextEntry::make('required_documents')
                    ->label('Giấy tờ cần chuẩn bị')
                    ->badge()
                    ->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('description')
                    ->label('Mô tả chi tiết')
                    ->html()
                    ->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('sort_order')->label('Thứ tự')->numeric(),
                TextEntry::make('created_at')->label('Ngày tạo')->dateTime('d/m/Y H:i'),
                TextEntry::make('deleted_at')
                    ->label('Đã xoá lúc')
                    ->dateTime('d/m/Y H:i')
                    ->visible(fn (VisaCountry $record): bool => $record->trashed()),
            ]);
    }
}