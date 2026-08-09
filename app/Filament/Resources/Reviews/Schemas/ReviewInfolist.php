<?php

namespace App\Filament\Resources\Reviews\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Modules\Review\Models\Review;

class ReviewInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('tour.name')->label('Tour'),
                TextEntry::make('customer_name')->label('Người đánh giá'),
                TextEntry::make('user.name')
                    ->label('Tài khoản')
                    ->placeholder('Khách vãng lai'),
                TextEntry::make('rating')
                    ->label('Số sao')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => $state.' ★'),
                TextEntry::make('content')
                    ->label('Nội dung')
                    ->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'approved' => 'Đã duyệt',
                        'rejected' => 'Từ chối',
                        default => 'Chờ duyệt',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    }),
                TextEntry::make('approvedBy.name')
                    ->label('Người duyệt')
                    ->placeholder('—'),
                TextEntry::make('approved_at')
                    ->label('Thời điểm duyệt')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
                TextEntry::make('admin_reply')
                    ->label('Phản hồi của công ty')
                    ->placeholder('Chưa phản hồi')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->label('Ngày gửi')
                    ->dateTime('d/m/Y H:i'),
                TextEntry::make('deleted_at')
                    ->label('Đã xoá lúc')
                    ->dateTime('d/m/Y H:i')
                    ->visible(fn (Review $record): bool => $record->trashed()),
            ]);
    }
}