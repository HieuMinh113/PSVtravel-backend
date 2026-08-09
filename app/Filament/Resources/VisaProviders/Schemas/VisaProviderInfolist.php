<?php

namespace App\Filament\Resources\VisaProviders\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Modules\Visa\Models\VisaProvider;

class VisaProviderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')->label('Tên đối tác'),
                TextEntry::make('contact_person')->label('Người liên hệ')->placeholder('—'),
                TextEntry::make('phone')->label('Số điện thoại')->placeholder('—')->copyable(),
                TextEntry::make('email')->label('Email')->placeholder('—')->copyable(),
                TextEntry::make('address')->label('Địa chỉ')->placeholder('—')->columnSpanFull(),
                TextEntry::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'active' ? 'Đang hợp tác' : 'Ngừng hợp tác')
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray'),
                TextEntry::make('note')->label('Ghi chú nội bộ')->placeholder('—')->columnSpanFull(),
                TextEntry::make('created_at')->label('Ngày tạo')->dateTime('d/m/Y H:i'),
                TextEntry::make('deleted_at')
                    ->label('Đã xoá lúc')
                    ->dateTime('d/m/Y H:i')
                    ->visible(fn (VisaProvider $record): bool => $record->trashed()),
            ]);
    }
}