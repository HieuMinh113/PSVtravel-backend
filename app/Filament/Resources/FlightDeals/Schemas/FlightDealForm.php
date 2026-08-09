<?php

namespace App\Filament\Resources\FlightDeals\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class FlightDealForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('airline_id')
                    ->label('Hãng bay')
                    ->relationship('airline', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('trip_type')
                    ->label('Loại vé')
                    ->options([
                        'one_way' => 'Một chiều',
                        'round_trip' => 'Khứ hồi',
                    ])
                    ->default('one_way')
                    ->required(),

                TextInput::make('from_city')
                    ->label('Điểm đi')
                    ->helperText('VD: Hồ Chí Minh')
                    ->required()
                    ->maxLength(255),
                TextInput::make('to_city')
                    ->label('Điểm đến')
                    ->helperText('VD: Hà Nội')
                    ->required()
                    ->maxLength(255),

                TextInput::make('price')
                    ->label('Giá tham khảo')
                    ->helperText('Giá chỉ mang tính tham khảo, khách liên hệ để chốt')
                    ->numeric()
                    ->minValue(1)
                    ->suffix('₫')
                    ->required(),
                TextInput::make('old_price')
                    ->label('Giá gốc (gạch ngang)')
                    ->numeric()
                    ->suffix('₫'),

                DatePicker::make('valid_from')
                    ->label('Áp dụng từ')
                    ->native(false)
                    ->displayFormat('d/m/Y'),
                DatePicker::make('valid_to')
                    ->label('Áp dụng đến')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->rules([
                        fn (Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                            $tu = $get('valid_from');
                            if ($tu && $value && strtotime($value) < strtotime($tu)) {
                                $fail('Ngày kết thúc phải sau ngày bắt đầu.');
                            }
                        },
                    ]),

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

                Textarea::make('note')
                    ->label('Ghi chú')
                    ->helperText('VD: Chưa gồm thuế phí, áp dụng ngày thường')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}