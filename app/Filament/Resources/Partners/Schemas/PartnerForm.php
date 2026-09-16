<?php

namespace App\Filament\Resources\Partners\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PartnerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Tên đối tác')
                    ->required()
                    ->maxLength(255),
                FileUpload::make('logo')
                    ->label('Logo')
                    ->image()
                    ->directory('partners')
                    ->disk('public')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('link_url')
                    ->label('Liên kết (không bắt buộc)')
                    ->url()
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
                    ->label('Thứ tự')
                    ->numeric()
                    ->default(0)
                    ->required(),
            ]);
    }
}
