<?php

namespace App\Filament\Resources\Moments\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Modules\Moment\Models\Moment;

class MomentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ImageEntry::make('image')
                    ->label('Ảnh')
                    ->columnSpanFull(),
                TextEntry::make('caption')
                    ->label('Chú thích')
                    ->placeholder('—'),
                TextEntry::make('customer_name')
                    ->label('Tên du khách')
                    ->placeholder('—'),
                TextEntry::make('tour.name')
                    ->label('Thuộc tour')
                    ->placeholder('Không gắn tour'),
                TextEntry::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'published' ? 'Đang hiển thị' : 'Đã ẩn')
                    ->color(fn (string $state): string => $state === 'published' ? 'success' : 'gray'),
                TextEntry::make('sort_order')
                    ->label('Thứ tự')
                    ->numeric(),
                TextEntry::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime('d/m/Y H:i'),
                TextEntry::make('updated_at')
                    ->label('Cập nhật lần cuối')
                    ->dateTime('d/m/Y H:i'),
                TextEntry::make('deleted_at')
                    ->label('Đã xoá lúc')
                    ->dateTime('d/m/Y H:i')
                    ->visible(fn (Moment $record): bool => $record->trashed()),
            ]);
    }
}