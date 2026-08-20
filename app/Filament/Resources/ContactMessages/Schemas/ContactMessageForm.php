<?php

namespace App\Filament\Resources\ContactMessages\Schemas;

use App\Models\ContactMessage;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ContactMessageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            // Thông tin khách gửi lên: chỉ đọc, không ai được sửa lời của khách
            TextInput::make('name')->label('Họ tên')->disabled(),
            TextInput::make('phone')->label('Số điện thoại')->disabled(),
            TextInput::make('email')->label('Email')->placeholder('Khách không để lại')->disabled(),
            TextInput::make('subject')->label('Chủ đề')->placeholder('Không có')->disabled(),
            Textarea::make('message')
                ->label('Nội dung khách gửi')
                ->rows(6)
                ->disabled()
                ->columnSpanFull(),

            Select::make('status')
                ->label('Trạng thái xử lý')
                ->options(ContactMessage::TRANG_THAI)
                ->default('new')
                ->required(),
            Textarea::make('admin_note')
                ->label('Ghi chú nội bộ')
                ->helperText('Đã gọi chưa, khách cần gì, ai đang theo — khách không thấy phần này.')
                ->rows(4)
                ->columnSpanFull(),
        ]);
    }
}
