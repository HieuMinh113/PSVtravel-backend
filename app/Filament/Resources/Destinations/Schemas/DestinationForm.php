<?php

namespace App\Filament\Resources\Destinations\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Modules\Category\Models\Category;

class DestinationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Tên điểm đến')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->label('Đường dẫn (slug)')
                    ->helperText('Không dấu, viết thường, ví dụ: da-nang')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                FileUpload::make('image')
                    ->label('Ảnh đại diện')
                    ->image()
                    ->imageEditor()
                    ->directory('destinations')
                    ->disk('public')
                    ->columnSpanFull(),
                TextInput::make('region')
                    ->label('Khu vực')
                    ->helperText('Ví dụ: Miền Trung, Đông Nam Á')
                    ->maxLength(255),
                Select::make('category_slug')
                    ->label('Danh mục tour liên quan')
                    ->helperText('Chọn danh mục để trang điểm đến tự hiện các tour thuộc danh mục đó')
                    ->options(fn () => Category::query()->pluck('name', 'slug')->all())
                    ->searchable(),
                Textarea::make('summary')
                    ->label('Mô tả ngắn')
                    ->helperText('Hiện ở thẻ danh sách điểm đến')
                    ->rows(2)
                    ->maxLength(500)
                    ->columnSpanFull(),
                RichEditor::make('description')
                    ->label('Giới thiệu chi tiết')
                    ->fileAttachmentsDisk('public')
                    ->fileAttachmentsDirectory('destinations/noi-dung')
                    ->fileAttachmentsVisibility('public')
                    ->columnSpanFull(),
                Toggle::make('is_featured')
                    ->label('Nổi bật ở trang chủ'),
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
