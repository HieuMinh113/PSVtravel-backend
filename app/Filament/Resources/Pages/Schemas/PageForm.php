<?php

namespace App\Filament\Resources\Pages\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Modules\Page\Models\Page;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Tiêu đề trang')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->label('Đường dẫn (slug)')
                    ->helperText('Không dấu, viết thường. Trang lõi không được đổi.')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->disabled(fn (?Page $record): bool => (bool) $record?->is_system)
                    ->dehydrated(),

                FileUpload::make('hero_image')
                    ->label('Ảnh đầu trang')
                    ->image()
                    ->directory('pages')
                    ->disk('public')
                    ->columnSpanFull(),

                RichEditor::make('body')
                    ->label('Nội dung trang')
                    ->columnSpanFull(),

                TextInput::make('meta_title')
                    ->label('Tiêu đề SEO')
                    ->helperText('Để trống thì dùng tiêu đề trang')
                    ->maxLength(255),
                Select::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'published' => 'Đang hiển thị',
                        'hidden' => 'Đã ẩn',
                    ])
                    ->default('published')
                    ->required(),

                Textarea::make('meta_description')
                    ->label('Mô tả SEO')
                    ->helperText('Khoảng 150–160 ký tự, hiện trên kết quả tìm kiếm Google')
                    ->rows(3)
                    ->maxLength(300)
                    ->columnSpanFull(),
            ]);
    }
}