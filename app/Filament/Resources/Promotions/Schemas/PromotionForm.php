<?php

namespace App\Filament\Resources\Promotions\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class PromotionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Tiêu đề ưu đãi')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                FileUpload::make('image')
                    ->label('Ảnh')
                    ->image()
                    ->imageEditor()
                    ->directory('promotions')
                    ->disk('public')
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label('Mô tả ngắn')
                    ->rows(2)
                    ->maxLength(500)
                    ->columnSpanFull(),
                TextInput::make('discount_label')
                    ->label('Nhãn giảm giá')
                    ->helperText('Ví dụ: -30%, Giảm 2 triệu')
                    ->maxLength(50),
                TextInput::make('badge')
                    ->label('Nhãn nổi bật')
                    ->helperText('Ví dụ: HOT, Sắp hết chỗ')
                    ->maxLength(50),
                TextInput::make('price')
                    ->label('Giá ưu đãi (đ)')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('old_price')
                    ->label('Giá gốc (đ)')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('link_url')
                    ->label('Liên kết')
                    ->helperText('Dán link tour/vé/visa để khách bấm vào, ví dụ /tour-trong-nuoc/da-nang-4n3d')
                    ->maxLength(255)
                    ->columnSpanFull(),
                DatePicker::make('ends_at')
                    ->label('Hạn khuyến mãi')
                    ->helperText('Để trống nếu không giới hạn. Quá hạn sẽ tự ẩn.')
                    ->native(false)
                    ->displayFormat('d/m/Y'),
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
