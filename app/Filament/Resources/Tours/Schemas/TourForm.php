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
                Select::make('categories')
                    ->label('Danh mục')
                    ->relationship('categories', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable(),
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
                    ->minValue(1)
                    ->default(1)
                    ->live(onBlur: true)
                    ->required(),
                TextInput::make('duration_nights')
                    ->label('Số đêm')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required()
                    ->rules([
                        fn (\Filament\Schemas\Components\Utilities\Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                            $ngay = (int) $get('duration_days');
                            if ((int) $value > $ngay) {
                                $fail('Số đêm không được nhiều hơn số ngày.');
                            }
                            if ((int) $value < $ngay - 1) {
                                $fail('Số đêm phải bằng số ngày hoặc ít hơn 1 (VD: 3 ngày 2 đêm).');
                            }
                        },
                    ]),

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
                    ->helperText('Tự tính từ các đánh giá đã duyệt')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),
                TextInput::make('review_count')
                    ->label('Số lượt đánh giá')
                    ->helperText('Tự tính từ các đánh giá đã duyệt')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),

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