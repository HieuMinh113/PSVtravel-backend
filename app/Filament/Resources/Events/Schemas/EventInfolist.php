<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Models\Event;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class EventInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ImageEntry::make('cover_image')
                    ->label('Ảnh bìa')
                    ->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('title')->label('Tên gói'),
                TextEntry::make('slug')->label('Đường dẫn'),
                TextEntry::make('audience')
                    ->label('Phù hợp với')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn (?string $state): string => \App\Models\Event::DOI_TUONG[$state] ?? $state)
                    ->placeholder('—'),
                TextEntry::make('group_size')->label('Số người phù hợp')->placeholder('—'),
                TextEntry::make('duration')->label('Thời lượng')->placeholder('—'),
                TextEntry::make('location')->label('Địa điểm gợi ý')->placeholder('—'),
                TextEntry::make('price_note')->label('Ghi chú giá')->placeholder('—'),
                IconEntry::make('is_featured')->label('Nổi bật')->boolean(),
                TextEntry::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'published' => 'Đang hiển thị',
                        'hidden' => 'Đã ẩn',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'hidden' => 'warning',
                        default => 'gray',
                    }),
                TextEntry::make('sort_order')->label('Thứ tự')->numeric(),
                TextEntry::make('summary')
                    ->label('Mô tả ngắn')
                    ->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('includes')
                    ->label('Sẽ có gì')
                    ->placeholder('—')
                    ->badge()
                    ->columnSpanFull(),
                TextEntry::make('description')
                    ->label('Giới thiệu chi tiết')
                    ->html()
                    ->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('created_at')->label('Ngày tạo')->dateTime('d/m/Y H:i'),
                TextEntry::make('updated_at')->label('Cập nhật lần cuối')->dateTime('d/m/Y H:i'),
                TextEntry::make('deleted_at')
                    ->label('Đã xoá lúc')
                    ->dateTime('d/m/Y H:i')
                    ->visible(fn (Event $record): bool => $record->trashed()),
            ]);
    }
}
