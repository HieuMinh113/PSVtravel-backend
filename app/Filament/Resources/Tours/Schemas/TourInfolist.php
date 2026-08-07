<?php

namespace App\Filament\Resources\Tours\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Modules\Tour\Models\Tour;

class TourInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ImageEntry::make('cover_image')
                    ->label('Ảnh bìa')
                    ->placeholder('—'),
                TextEntry::make('name')
                    ->label('Tên tour'),
                TextEntry::make('slug')
                    ->label('Đường dẫn'),
                TextEntry::make('type')
                    ->label('Loại tour')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'abroad' ? 'Nước ngoài' : 'Trong nước'),
                TextEntry::make('region')
                    ->label('Vùng / khu vực')
                    ->placeholder('—'),
                TextEntry::make('country')
                    ->label('Quốc gia')
                    ->placeholder('—'),
                TextEntry::make('departure_from')
                    ->label('Khởi hành từ')
                    ->placeholder('—'),
                TextEntry::make('duration_days')
                    ->label('Số ngày')
                    ->numeric(),
                TextEntry::make('duration_nights')
                    ->label('Số đêm')
                    ->numeric(),

                TextEntry::make('adult_price')
                    ->label('Giá người lớn')
                    ->money('VND'),
                TextEntry::make('child_price')
                    ->label('Giá trẻ em')
                    ->money('VND')
                    ->placeholder('—'),
                TextEntry::make('old_price')
                    ->label('Giá gốc')
                    ->money('VND')
                    ->placeholder('—'),
                TextEntry::make('tag')
                    ->label('Nhãn')
                    ->placeholder('—'),

                TextEntry::make('highlights')
                    ->label('Điểm nổi bật')
                    ->badge()
                    ->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('included')
                    ->label('Dịch vụ bao gồm')
                    ->badge()
                    ->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('excluded')
                    ->label('Không bao gồm')
                    ->badge()
                    ->placeholder('—')
                    ->columnSpanFull(),

                TextEntry::make('cancellation_policy')
                    ->label('Chính sách huỷ tour')
                    ->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('description')
                    ->label('Mô tả')
                    ->placeholder('—')
                    ->columnSpanFull(),

                TextEntry::make('rating')
                    ->label('Đánh giá (sao)')
                    ->numeric()
                    ->placeholder('—'),
                TextEntry::make('review_count')
                    ->label('Số lượt đánh giá')
                    ->numeric(),
                TextEntry::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'published' => 'Đang bán',
                        'hidden' => 'Đã ẩn',
                        default => 'Nháp',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'hidden' => 'warning',
                        default => 'gray',
                    }),
                IconEntry::make('is_featured')
                    ->label('Tour nổi bật')
                    ->boolean(),
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
                    ->visible(fn (Tour $record): bool => $record->trashed()),
            ]);
    }
}