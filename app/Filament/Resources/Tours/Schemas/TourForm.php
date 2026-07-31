<?php

namespace App\Filament\Resources\Tours\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TourForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Tên tour')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->label('Đường dẫn (slug)')
                    ->helperText('Dùng trên URL, không dấu, viết thường. VD: tour-da-nang-3-ngay')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                Select::make('type')
                    ->label('Loại tour')
                    ->options([
                        'domestic' => 'Trong nước',
                        'abroad' => 'Nước ngoài',
                    ])
                    ->default('domestic')
                    ->required(),
                TextInput::make('region')
                    ->label('Vùng / khu vực')
                    ->maxLength(255),
                TextInput::make('country')
                    ->label('Quốc gia')
                    ->helperText('Chỉ điền với tour nước ngoài')
                    ->maxLength(255),
                TextInput::make('departure_from')
                    ->label('Khởi hành từ')
                    ->maxLength(255),

                TextInput::make('duration_days')
                    ->label('Số ngày')
                    ->numeric()
                    ->default(1)
                    ->required(),
                TextInput::make('duration_nights')
                    ->label('Số đêm')
                    ->numeric()
                    ->default(0)
                    ->required(),

                TextInput::make('adult_price')
                    ->label('Giá người lớn')
                    ->numeric()
                    ->default(0)
                    ->suffix('₫')
                    ->required(),
                TextInput::make('child_price')
                    ->label('Giá trẻ em')
                    ->numeric()
                    ->suffix('₫'),
                TextInput::make('old_price')
                    ->label('Giá gốc (gạch ngang)')
                    ->numeric()
                    ->suffix('₫'),

                TextInput::make('tag')
                    ->label('Nhãn (Bán chạy / Mới…)')
                    ->maxLength(255),
                FileUpload::make('cover_image')
                    ->label('Ảnh bìa')
                    ->image()
                    ->directory('tours'),

                TagsInput::make('highlights')
                    ->label('Điểm nổi bật')
                    ->helperText('Gõ từng ý rồi nhấn Enter')
                    ->columnSpanFull(),
                TagsInput::make('included')
                    ->label('Dịch vụ bao gồm')
                    ->helperText('Gõ từng mục rồi nhấn Enter')
                    ->columnSpanFull(),
                TagsInput::make('excluded')
                    ->label('Không bao gồm')
                    ->helperText('Gõ từng mục rồi nhấn Enter')
                    ->columnSpanFull(),

                Textarea::make('cancellation_policy')
                    ->label('Chính sách huỷ tour')
                    ->rows(4)
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label('Mô tả')
                    ->rows(6)
                    ->columnSpanFull(),

                TextInput::make('rating')
                    ->label('Đánh giá (sao)')
                    ->helperText('Nhập tay tạm thời, từ 0 đến 5')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(5)
                    ->step(0.1),
                TextInput::make('review_count')
                    ->label('Số lượt đánh giá')
                    ->numeric()
                    ->default(0)
                    ->required(),

                Select::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'draft' => 'Nháp',
                        'published' => 'Đang bán',
                        'hidden' => 'Đã ẩn',
                    ])
                    ->default('draft')
                    ->required(),
                Toggle::make('is_featured')
                    ->label('Tour nổi bật'),
                TextInput::make('sort_order')
                    ->label('Thứ tự sắp xếp')
                    ->numeric()
                    ->default(0)
                    ->required(),
            ]);
    }
}