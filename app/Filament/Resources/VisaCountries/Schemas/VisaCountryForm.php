<?php

namespace App\Filament\Resources\VisaCountries\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class VisaCountryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Quốc gia')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->label('Đường dẫn (slug)')
                    ->helperText('Không dấu, viết thường. VD: han-quoc')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                FileUpload::make('flag_image')
                    ->label('Ảnh cờ / ảnh đại diện')
                    ->image()
                    ->directory('visa')
                    ->disk('public'),
                Select::make('visa_type')
                    ->label('Loại visa')
                    ->options([
                        'tourist' => 'Du lịch',
                        'business' => 'Công tác',
                        'study' => 'Du học',
                    ])
                    ->default('tourist')
                    ->required(),

                TextInput::make('price')
                    ->label('Chi phí dịch vụ')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->suffix('₫')
                    ->required(),
                TextInput::make('processing_time')
                    ->label('Thời gian xử lý')
                    ->helperText('VD: 7-10 ngày làm việc')
                    ->maxLength(255),

                TextInput::make('success_rate')
                    ->label('Tỷ lệ đậu (%)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->suffix('%'),
                Select::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'published' => 'Đang hiển thị',
                        'hidden' => 'Đã ẩn',
                    ])
                    ->default('published')
                    ->required(),

                TagsInput::make('required_documents')
                    ->label('Giấy tờ cần chuẩn bị')
                    ->helperText('Gõ từng loại giấy tờ rồi nhấn Enter')
                    ->columnSpanFull(),

                RichEditor::make('description')
                    ->label('Mô tả chi tiết')
                    ->columnSpanFull(),

                TextInput::make('sort_order')
                    ->label('Thứ tự')
                    ->numeric()
                    ->default(0)
                    ->required(),
            ]);
    }
}