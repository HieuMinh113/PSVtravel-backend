<?php

namespace App\Filament\Resources\Airlines\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class AirlineForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Mã hãng')
                    ->helperText('Mã IATA 2 ký tự. VD: VN, VJ, QH')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(10),
                TextInput::make('name')
                    ->label('Tên hãng bay')
                    ->required()
                    ->maxLength(255),
                FileUpload::make('logo')
                    ->label('Logo')
                    ->image()
                    ->directory('airlines'),
                TextInput::make('country')
                    ->label('Quốc gia')
                    ->maxLength(255),
                TextInput::make('website')
                    ->label('Website')
                    ->url()
                    ->prefix('https://')
                    ->maxLength(255),
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
                    ->label('Ghi chú nội bộ')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}