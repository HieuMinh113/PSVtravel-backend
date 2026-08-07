<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ImageEntry::make('avatar')
                    ->label('Ảnh đại diện')
                    ->circular()
                    ->placeholder('—'),
                TextEntry::make('name')
                    ->label('Họ và tên'),
                TextEntry::make('email')
                    ->label('Email')
                    ->copyable(),
                TextEntry::make('roles.name')
                    ->label('Vai trò')
                    ->badge()
                    ->placeholder('Chưa gán'),
                TextEntry::make('phone')
                    ->label('Số điện thoại')
                    ->placeholder('—'),
                TextEntry::make('locale')
                    ->label('Ngôn ngữ'),
                TextEntry::make('loyalty_points')
                    ->label('Điểm tích luỹ')
                    ->numeric(),
                TextEntry::make('email_verified_at')
                    ->label('Thời điểm xác thực email')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Chưa xác thực'),
                TextEntry::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime('d/m/Y H:i'),
                TextEntry::make('updated_at')
                    ->label('Cập nhật lần cuối')
                    ->dateTime('d/m/Y H:i'),
            ]);
    }
}