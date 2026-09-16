<?php

namespace App\Filament\Resources\Faqs\Schemas;

use App\Models\Faq;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class FaqForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('question')
                    ->label('Câu hỏi')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Textarea::make('answer')
                    ->label('Câu trả lời')
                    ->required()
                    ->rows(5)
                    ->columnSpanFull(),
                Select::make('category')
                    ->label('Chuyên mục')
                    ->options(Faq::NHOM)
                    ->default('chung')
                    ->required(),
                TextInput::make('sort_order')
                    ->label('Thứ tự')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Select::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'published' => 'Đang hiển thị',
                        'hidden' => 'Đã ẩn',
                    ])
                    ->default('published')
                    ->required(),
            ]);
    }
}
