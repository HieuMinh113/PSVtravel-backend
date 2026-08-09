<?php

namespace App\Filament\Resources\Guides\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Modules\Guide\Models\Guide;

class GuideInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ImageEntry::make('cover_image')
                    ->label('Ảnh bìa')
                    ->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('title')
                    ->label('Tiêu đề'),
                TextEntry::make('slug')
                    ->label('Đường dẫn'),
                TextEntry::make('category')
                    ->label('Chuyên mục')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'kinh-nghiem' => 'Kinh nghiệm du lịch',
                        'am-thuc' => 'Ẩm thực',
                        'thu-tuc' => 'Thủ tục giấy tờ',
                        'diem-den' => 'Điểm đến',
                        'khac' => 'Khác',
                        default => '—',
                    }),
                TextEntry::make('author.name')
                    ->label('Tác giả')
                    ->placeholder('—'),
                TextEntry::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'published' => 'Đã đăng',
                        'hidden' => 'Đã ẩn',
                        default => 'Nháp',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'hidden' => 'warning',
                        default => 'gray',
                    }),
                TextEntry::make('published_at')
                    ->label('Ngày đăng')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
                TextEntry::make('view_count')
                    ->label('Lượt xem')
                    ->numeric(),
                TextEntry::make('sort_order')
                    ->label('Thứ tự')
                    ->numeric(),
                TextEntry::make('excerpt')
                    ->label('Tóm tắt')
                    ->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('content')
                    ->label('Nội dung bài viết')
                    ->html()
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
                    ->visible(fn (Guide $record): bool => $record->trashed()),
            ]);
    }
}