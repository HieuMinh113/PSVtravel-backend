<?php

namespace App\Filament\Resources\AboutImages\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AboutImageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('image')
                    ->label('Ảnh')
                    ->helperText('Ảnh hoạt động, hậu trường, đội ngũ... hiển thị ở trang "Về chúng tôi"')
                    ->image()
                    ->imageEditor()
                    ->directory('about')
                    ->disk('public')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('caption')
                    ->label('Chú thích')
                    ->helperText('Hiện khi rê chuột / xem ảnh lớn (không bắt buộc)')
                    ->maxLength(255)
                    ->columnSpanFull(),
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
