<?php

namespace App\Filament\Resources\FlightDeals\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Modules\Flight\Models\FlightDeal;

class FlightDealInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('airline.name')->label('Hãng bay'),
                TextEntry::make('trip_type')
                    ->label('Loại vé')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'round_trip' ? 'Khứ hồi' : 'Một chiều'),
                TextEntry::make('from_city')->label('Điểm đi'),
                TextEntry::make('to_city')->label('Điểm đến'),
                TextEntry::make('price')->label('Giá tham khảo')->money('VND')->weight('bold'),
                TextEntry::make('old_price')->label('Giá gốc')->money('VND')->placeholder('—'),
                TextEntry::make('valid_from')->label('Áp dụng từ')->date('d/m/Y')->placeholder('Ngay lập tức'),
                TextEntry::make('valid_to')->label('Áp dụng đến')->date('d/m/Y')->placeholder('Không giới hạn'),
                TextEntry::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'published' ? 'Đang hiển thị' : 'Đã ẩn')
                    ->color(fn (string $state): string => $state === 'published' ? 'success' : 'gray'),
                TextEntry::make('sort_order')->label('Thứ tự')->numeric(),
                TextEntry::make('note')->label('Ghi chú')->placeholder('—')->columnSpanFull(),
                TextEntry::make('created_at')->label('Ngày tạo')->dateTime('d/m/Y H:i'),
                TextEntry::make('deleted_at')
                    ->label('Đã xoá lúc')
                    ->dateTime('d/m/Y H:i')
                    ->visible(fn (FlightDeal $record): bool => $record->trashed()),
            ]);
    }
}