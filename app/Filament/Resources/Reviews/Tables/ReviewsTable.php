<?php

namespace App\Filament\Resources\Reviews\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Modules\Review\Models\Review;
use Filament\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
class ReviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Ngày gửi')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('customer_name')
                    ->label('Người đánh giá')
                    ->searchable(),
                TextColumn::make('tour.name')
                    ->label('Tour')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('rating')
                    ->label('Sao')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => $state.' ★')
                    ->color(fn (int $state): string => match (true) {
                        $state >= 4 => 'success',
                        $state === 3 => 'warning',
                        default => 'danger',
                    })
                    ->sortable(),
                TextColumn::make('content')
                    ->label('Nội dung')
                    ->limit(60)
                    ->tooltip(fn (Review $record): ?string => $record->content)
                    ->wrap(),
                TextColumn::make('status')
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
                TextColumn::make('approvedBy.name')
                    ->label('Người duyệt')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'pending' => 'Chờ duyệt',
                        'approved' => 'Đã duyệt',
                        'rejected' => 'Từ chối',
                    ]),
                SelectFilter::make('rating')
                    ->label('Số sao')
                    ->options([
                        1 => '1 sao', 2 => '2 sao', 3 => '3 sao', 4 => '4 sao', 5 => '5 sao',
                    ]),
                TrashedFilter::make()->label('Đã xoá'),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Duyệt')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (Review $record): bool => $record->status !== 'approved')
                    ->requiresConfirmation()
                    ->modalHeading('Duyệt đánh giá')
                    ->modalDescription('Đánh giá sẽ hiển thị công khai và được tính vào điểm sao của tour.')
                    ->action(function (Review $record): void {
                        $record->update([
                            'status' => 'approved',
                            'approved_by' => Auth::id(),
                            'approved_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Đã duyệt đánh giá')
                            ->body('Điểm sao của tour đã được tính lại.')
                            ->success()
                            ->send();
                    }),
                Action::make('reject')
                    ->label('Từ chối')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->visible(fn (Review $record): bool => $record->status !== 'rejected')
                    ->requiresConfirmation()
                    ->modalHeading('Từ chối đánh giá')
                    ->modalDescription('Đánh giá sẽ bị ẩn và không tính vào điểm sao của tour.')
                    ->action(function (Review $record): void {
                        $record->update([
                            'status' => 'rejected',
                            'approved_by' => Auth::id(),
                            'approved_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Đã từ chối đánh giá')
                            ->success()
                            ->send();
                    }),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkAction::make('approveMany')
                    ->label('Duyệt các đánh giá đã chọn')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Duyệt hàng loạt')
                    ->modalDescription('Các đánh giá được chọn sẽ hiển thị công khai và tính vào điểm sao của tour.')
                    ->action(function (Collection $records): void {
                        $soLuong = 0;

                        foreach ($records as $record) {
                            if ($record->status === 'approved') {
                                continue;
                            }

                            $record->update([
                                'status' => 'approved',
                                'approved_by' => Auth::id(),
                                'approved_at' => now(),
                            ]);

                            $soLuong++;
                        }

                        Notification::make()
                            ->title('Đã duyệt '.$soLuong.' đánh giá')
                            ->body('Điểm sao của các tour liên quan đã được tính lại.')
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}