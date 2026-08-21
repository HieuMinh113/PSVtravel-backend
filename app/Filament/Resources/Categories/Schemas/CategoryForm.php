<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label('Nhóm')
                    ->options([
                        'domestic' => 'Trong nước',
                        'abroad' => 'Nước ngoài',
                    ])
                    ->default('domestic')
                    ->required(),
                TextInput::make('name')
                    ->label('Tên danh mục')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->label('Đường dẫn (slug)')
                    ->helperText('Không dấu, viết thường. VD: mien-trung')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                FileUpload::make('image')
                    ->label('Ảnh đại diện')
                    ->image()
                    ->directory('categories')
                    ->disk('public'),
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
                Textarea::make('description')
                    ->label('Mô tả')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}