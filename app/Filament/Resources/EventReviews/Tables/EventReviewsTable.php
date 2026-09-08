<?php

namespace App\Filament\Resources\EventReviews\Tables;

use App\Models\EventReview;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class EventReviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Gửi lúc')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('event.title')
                    ->label('Gói')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('customer_name')
                    ->label('Khách')
                    ->weight('semibold')
                    ->searchable(),
                TextColumn::make('rating')
                    ->label('Sao')
                    ->formatStateUsing(fn (int $state): string => str_repeat('★', $state).str_repeat('☆', 5 - $state))
                    ->color('warning'),
                TextColumn::make('content')
                    ->label('Nội dung')
                    ->limit(60)
                    ->tooltip(fn (EventReview $record): ?string => $record->content)
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => EventReview::TRANG_THAI[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(EventReview::TRANG_THAI),
                TrashedFilter::make()->label('Đã xoá'),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Duyệt')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (EventReview $record): bool => ! $record->trashed() && $record->status !== 'approved')
                    ->requiresConfirmation()
                    ->modalHeading('Duyệt đánh giá này')
                    ->modalDescription('Đánh giá sẽ hiển thị công khai trên trang gói sự kiện.')
                    ->action(function (EventReview $record): void {
                        $record->update([
                            'status' => 'approved',
                            'approved_by' => Auth::id(),
                            'approved_at' => now(),
                        ]);
                        Notification::make()->title('Đã duyệt đánh giá')->success()->send();
                    }),
                Action::make('reject')
                    ->label('Từ chối')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (EventReview $record): bool => ! $record->trashed() && $record->status !== 'rejected')
                    ->requiresConfirmation()
                    ->modalHeading('Từ chối đánh giá này')
                    ->action(function (EventReview $record): void {
                        $record->update(['status' => 'rejected']);
                        Notification::make()->title('Đã từ chối đánh giá')->success()->send();
                    }),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
