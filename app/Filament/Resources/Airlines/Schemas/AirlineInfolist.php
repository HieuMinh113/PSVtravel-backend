<?php

namespace App\Filament\Resources\Airlines\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Modules\Flight\Models\Airline;

class AirlineInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ImageEntry::make('logo')->label('Logo')->placeholder('—'),
                TextEntry::make('code')->label('Mã hãng')->badge(),
                TextEntry::make('name')->label('Tên hãng bay'),
                TextEntry::make('country')->label('Quốc gia')->placeholder('—'),
                TextEntry::make('website')->label('Website')->placeholder('—')->url(fn ($state) => $state),
                TextEntry::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'published' ? 'Đang hiển thị' : 'Đã ẩn')
                    ->color(fn (string $state): string => $state === 'published' ? 'success' : 'gray'),
                TextEntry::make('sort_order')->label('Thứ tự')->numeric(),
                TextEntry::make('note')->label('Ghi chú nội bộ')->placeholder('—')->columnSpanFull(),
                TextEntry::make('created_at')->label('Ngày tạo')->dateTime('d/m/Y H:i'),
                TextEntry::make('updated_at')->label('Cập nhật lần cuối')->dateTime('d/m/Y H:i'),
                TextEntry::make('deleted_at')
                    ->label('Đã xoá lúc')
                    ->dateTime('d/m/Y H:i')
                    ->visible(fn (Airline $record): bool => $record->trashed()),
            ]);
    }
}