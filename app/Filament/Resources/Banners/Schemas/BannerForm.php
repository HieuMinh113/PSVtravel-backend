<?php

namespace App\Filament\Resources\Banners\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class BannerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Tiêu đề')
                    ->required()
                    ->maxLength(255),
                TextInput::make('subtitle')
                    ->label('Dòng phụ')
                    ->maxLength(255),

                FileUpload::make('image')
                    ->label('Ảnh banner (máy tính)')
                    ->helperText('Nên dùng ảnh ngang, khoảng 1920×700')
                    ->image()
                    ->directory('banners')
                    ->required()
                    ->columnSpanFull(),
                FileUpload::make('image_mobile')
                    ->label('Ảnh banner (điện thoại)')
                    ->helperText('Để trống thì dùng chung ảnh máy tính. Nên dùng ảnh dọc hơn, khoảng 800×1000')
                    ->image()
                    ->directory('banners')
                    ->columnSpanFull(),

                TextInput::make('link')
                    ->label('Liên kết khi bấm vào')
                    ->helperText('Để trống nếu banner không bấm được. VD: /tour-trong-nuoc')
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
                    ->label('Thứ tự hiển thị')
                    ->helperText('Số nhỏ hiện trước')
                    ->numeric()
                    ->default(0)
                    ->required(),

                DateTimePicker::make('start_at')
                    ->label('Bắt đầu hiển thị')
                    ->helperText('Để trống nghĩa là hiển thị ngay')
                    ->native(false)
                    ->displayFormat('d/m/Y H:i')
                    ->seconds(false),
                DateTimePicker::make('end_at')
                    ->label('Kết thúc hiển thị')
                    ->helperText('Để trống nghĩa là hiển thị mãi')
                    ->native(false)
                    ->displayFormat('d/m/Y H:i')
                    ->seconds(false)
                    ->after('start_at')
                    ->rules([
                        fn (Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                            $batDau = $get('start_at');
                            if ($batDau && $value && strtotime($value) <= strtotime($batDau)) {
                                $fail('Thời điểm kết thúc phải sau thời điểm bắt đầu.');
                            }
                        },
                    ]),
            ]);
    }
}