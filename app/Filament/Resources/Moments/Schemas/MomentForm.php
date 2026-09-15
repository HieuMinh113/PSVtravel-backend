<?php

namespace App\Filament\Resources\Moments\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MomentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('image')
                    ->label('Ảnh chính')
                    ->helperText('Ảnh đại diện hiển thị ngoài trang')
                    ->image()
                    ->imageEditor()
                    ->directory('moments')
                    ->disk('public')
                    ->required()
                    ->columnSpanFull(),
                FileUpload::make('gallery')
                    ->label('Ảnh phụ (bộ sưu tập)')
                    ->helperText('Có thể thêm nhiều ảnh — kéo thả để sắp thứ tự. Khách bấm vào khoảnh khắc sẽ xem được tất cả.')
                    ->image()
                    ->multiple()
                    ->reorderable()
                    ->appendFiles()
                    ->directory('moments')
                    ->disk('public')
                    ->columnSpanFull(),
                TextInput::make('caption')
                    ->label('Chú thích')
                    ->maxLength(255),
                TextInput::make('customer_name')
                    ->label('Tên du khách')
                    ->maxLength(255),
                Select::make('tour_id')
                    ->label('Thuộc tour')
                    ->helperText('Để trống nếu không gắn tour cụ thể')
                    ->relationship('tour', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'published' => 'Đang hiển thị',
                        'hidden' => 'Đã ẩn',
                    ])
                    ->default('published')
                    ->required(),
                TextInput::make('sort_order')
                    ->label('Thứ tự')
                    ->numeric()
                    ->default(0)
                    ->required(),
            ]);
    }
}