<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Modules\Category\Models\Category;

class CategoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ImageEntry::make('image')
                    ->label('Ảnh đại diện')
                    ->placeholder('—'),
                TextEntry::make('name')
                    ->label('Tên danh mục'),
                TextEntry::make('slug')
                    ->label('Đường dẫn'),
                TextEntry::make('type')
                    ->label('Nhóm')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'abroad' ? 'Nước ngoài' : 'Trong nước'),
                TextEntry::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'published' ? 'Đang hiển thị' : 'Đã ẩn')
                    ->color(fn (string $state): string => $state === 'published' ? 'success' : 'gray'),
                TextEntry::make('sort_order')
                    ->label('Thứ tự')
                    ->numeric(),
                TextEntry::make('description')
                    ->label('Mô tả')
                    ->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime('d/m/Y H:i'),
                TextEntry::make('updated_at')
                    ->label('Cập nhật lần cuối')
                    ->dateTime('d/m/Y H:i'),
                TextEntry::make('deleted_at')
                    ->label('Đã xoá lúc')
                    ->dateTime('d/m/Y H:i')
                    ->visible(fn (Category $record): bool => $record->trashed()),
            ]);
    }
}