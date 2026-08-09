<?php

namespace App\Filament\Resources\Pages\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Modules\Page\Models\Page;

class PageInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ImageEntry::make('hero_image')
                    ->label('Ảnh đầu trang')
                    ->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('title')->label('Tiêu đề trang'),
                TextEntry::make('slug')->label('Đường dẫn')->badge(),
                TextEntry::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'published' ? 'Đang hiển thị' : 'Đã ẩn')
                    ->color(fn (string $state): string => $state === 'published' ? 'success' : 'gray'),
                IconEntry::make('is_system')->label('Trang lõi')->boolean(),
                TextEntry::make('body')
                    ->label('Nội dung trang')
                    ->html()
                    ->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('meta_title')->label('Tiêu đề SEO')->placeholder('—'),
                TextEntry::make('meta_description')
                    ->label('Mô tả SEO')
                    ->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('created_at')->label('Ngày tạo')->dateTime('d/m/Y H:i'),
                TextEntry::make('updated_at')->label('Sửa lần cuối')->dateTime('d/m/Y H:i'),
                TextEntry::make('deleted_at')
                    ->label('Đã xoá lúc')
                    ->dateTime('d/m/Y H:i')
                    ->visible(fn (Page $record): bool => $record->trashed()),
            ]);
    }
}