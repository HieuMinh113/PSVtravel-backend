<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;
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
                    ->maxLength(255)
                    // Hai danh mục trùng tên thì người nhập tour không phân biệt
                    // được chọn cái nào — chặn từ đầu.
                    ->unique(ignoreRecord: true)
                    ->validationMessages([
                        'unique' => 'Đã có danh mục tên này rồi.',
                    ])
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, $state, Set $set) {
                        if ($operation === 'create') {
                            $set('slug', Str::slug((string) $state));
                        }
                    }),
                TextInput::make('slug')
                    ->label('Đường dẫn (slug)')
                    ->helperText('Tự điền theo tên danh mục. Chỉ gồm chữ thường không dấu, số và dấu gạch ngang.')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->dehydrateStateUsing(fn (?string $state): string => Str::slug((string) $state))
                    ->rule('regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                    ->validationMessages([
                        'regex' => 'Đường dẫn chỉ được gồm chữ thường không dấu, số và dấu gạch ngang. VD: mien-trung',
                    ]),
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