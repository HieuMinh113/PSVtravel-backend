<?php

namespace App\Filament\Resources\Settings\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Modules\Page\Models\Setting;

class SettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('label')
                    ->label('Tên cấu hình')
                    ->disabled()
                    ->dehydrated(false),

                TextInput::make('key')
                    ->label('Mã khoá')
                    ->helperText('Do hệ thống quy định, không được đổi')
                    ->disabled()
                    ->dehydrated(false),

                // Ô nhập đổi kiểu theo loại cấu hình
                TextInput::make('value')
                    ->label('Giá trị')
                    ->maxLength(255)
                    ->columnSpanFull()
                    ->visible(fn (?Setting $record): bool => in_array($record?->type, ['text', null], true)),

                TextInput::make('value')
                    ->label('Đường dẫn')
                    ->url()
                    ->maxLength(255)
                    ->helperText('Nhập đầy đủ, VD: https://facebook.com/psvtravel')
                    ->columnSpanFull()
                    ->visible(fn (?Setting $record): bool => $record?->type === 'url'),

                Textarea::make('value')
                    ->label('Giá trị')
                    ->rows(4)
                    ->columnSpanFull()
                    ->visible(fn (?Setting $record): bool => $record?->type === 'textarea'),

                FileUpload::make('value')
                    ->label('Ảnh')
                    ->image()
                    ->directory('settings')
                    ->columnSpanFull()
                    ->visible(fn (?Setting $record): bool => $record?->type === 'image'),
            ]);
    }
}