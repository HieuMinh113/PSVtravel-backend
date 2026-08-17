<?php

namespace App\Filament\Resources\Guides\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class GuideForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Tiêu đề')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->label('Đường dẫn (slug)')
                    ->helperText('Không dấu, viết thường')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                Select::make('category')
                    ->label('Chuyên mục')
                    ->options([
                        'kinh-nghiem' => 'Kinh nghiệm du lịch',
                        'am-thuc' => 'Ẩm thực',
                        'thu-tuc' => 'Thủ tục giấy tờ',
                        'diem-den' => 'Điểm đến',
                        'khac' => 'Khác',
                    ])
                    ->searchable(),
                Select::make('author_id')
                    ->label('Tác giả')
                    ->relationship('author', 'name')
                    ->searchable()
                    ->preload(),

                FileUpload::make('cover_image')
                    ->label('Ảnh bìa')
                    ->image()
                    ->directory('guides')
                    ->columnSpanFull(),

                Textarea::make('excerpt')
                    ->label('Tóm tắt')
                    ->helperText('Đoạn ngắn hiện ở danh sách bài viết')
                    ->rows(3)
                    ->maxLength(500)
                    ->columnSpanFull(),
                RichEditor::make('content')
                    ->label('Nội dung bài viết')
                    ->columnSpanFull(),

                Select::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'draft' => 'Nháp',
                        'published' => 'Đã đăng',
                        'hidden' => 'Đã ẩn',
                    ])
                    ->default('draft')
                    ->required(),
                DateTimePicker::make('published_at')
                    ->label('Thời điểm đăng')
                    ->helperText('Để trống thì đăng ngay khi chuyển sang Đã đăng')
                    ->native(false)
                    ->displayFormat('d/m/Y H:i')
                    ->seconds(false),

                TextInput::make('view_count')
                    ->label('Lượt xem')
                    ->numeric()
                    ->default(0)
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('sort_order')
                    ->label('Thứ tự')
                    ->numeric()
                    ->default(0)
                    ->required(),
            ]);
    }
}