<?php

namespace App\Filament\Resources\Banners\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Modules\Banner\Models\Banner;

class BannerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('position')
                    ->label('Vị trí trên website')
                    ->options(Banner::VI_TRI)
                    ->default('promo')
                    ->required()
                    ->live()
                    ->helperText('Chọn "Ảnh vòng xoay" nếu muốn ảnh này chạy trong vòng tròn ở đầu trang.')
                    ->columnSpanFull(),

                TextInput::make('title')
                    ->label('Tiêu đề')
                    // Ảnh vòng xoay chỉ là ảnh trang trí, không hiện chữ nên không bắt buộc
                    ->required(fn (Get $get): bool => $get('position') === 'promo')
                    ->visible(fn (Get $get): bool => $get('position') === 'promo')
                    ->maxLength(255),
                TextInput::make('subtitle')
                    ->label('Dòng phụ')
                    ->visible(fn (Get $get): bool => $get('position') === 'promo')
                    ->maxLength(255),

                FileUpload::make('image')
                    ->label(fn (Get $get): string => match ($get('position')) {
                        'promo' => 'Ảnh banner (máy tính)',
                        'popup' => 'Ảnh poster (máy tính)',
                        default => 'Ảnh vòng xoay',
                    })
                    ->helperText(fn (Get $get): string => match ($get('position')) {
                        'promo' => 'Nên dùng ảnh ngang, khoảng 1920×700',
                        'popup' => 'Poster bật lên giữa màn hình. Nên dùng ảnh dọc/vuông, khoảng 800×1000.',
                        default => 'Ảnh sẽ hiện trong khung vuông nhỏ. Nên cắt VUÔNG khoảng 600×600, chủ thể nằm giữa. Mỗi trang nên có 8–12 ảnh.',
                    })
                    ->image()
                    ->directory('banners')
                    ->disk('public')
                    ->required()
                    ->columnSpanFull(),
                FileUpload::make('image_mobile')
                    ->label(fn (Get $get): string => $get('position') === 'popup' ? 'Ảnh poster (điện thoại)' : 'Ảnh banner (điện thoại)')
                    ->helperText('Để trống thì dùng chung ảnh máy tính. Nên dùng ảnh dọc hơn, khoảng 800×1000')
                    ->image()
                    ->directory('banners')
                    ->disk('public')
                    ->visible(fn (Get $get): bool => in_array($get('position'), ['promo', 'popup'], true))
                    ->columnSpanFull(),

                TextInput::make('link')
                    ->label('Liên kết khi bấm vào')
                    ->helperText('Để trống nếu không cần bấm. VD: /tour-trong-nuoc hoặc /team-building')
                    ->visible(fn (Get $get): bool => in_array($get('position'), ['promo', 'popup'], true))
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