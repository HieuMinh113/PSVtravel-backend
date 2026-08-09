<?php

namespace App\Filament\Resources\VisaProviders\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class VisaProviderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Tên đối tác')
                    ->required()
                    ->maxLength(255),
                TextInput::make('contact_person')
                    ->label('Người liên hệ')
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label('Số điện thoại')
                    ->tel()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->maxLength(255),
                TextInput::make('address')
                    ->label('Địa chỉ')
                    ->maxLength(255)
                    ->columnSpanFull(),
                Select::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'active' => 'Đang hợp tác',
                        'inactive' => 'Ngừng hợp tác',
                    ])
                    ->default('active')
                    ->required(),
                Textarea::make('note')
                    ->label('Ghi chú nội bộ')
                    ->helperText('Thông tin nội bộ, không hiển thị ra web')
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }
}