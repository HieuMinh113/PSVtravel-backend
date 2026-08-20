<?php

namespace App\Filament\Resources\ContactMessages\Tables;

use App\Models\ContactMessage;
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

class ContactMessagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Nhận lúc')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Khách')
                    ->weight('semibold')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Điện thoại')
                    ->copyable()
                    ->copyMessage('Đã chép số điện thoại')
                    ->searchable(),
                TextColumn::make('message')
                    ->label('Nội dung')
                    ->limit(70)
                    ->tooltip(fn (ContactMessage $record): string => $record->message)
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ContactMessage::TRANG_THAI[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'done' => 'success',
                        'handling' => 'info',
                        default => 'danger',
                    }),
                TextColumn::make('handledBy.name')
                    ->label('Người xử lý')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(ContactMessage::TRANG_THAI),
                TrashedFilter::make()->label('Đã xoá'),
            ])
            ->recordActions([
                // Một nút để đóng tin sau khi đã gọi khách — khỏi phải mở form sửa
                Action::make('done')
                    ->label('Đã xử lý')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ContactMessage $record): bool => ! $record->trashed() && $record->status !== 'done')
                    ->requiresConfirmation()
                    ->modalHeading('Đánh dấu đã xử lý')
                    ->modalDescription('Chỉ bấm sau khi đã liên hệ được với khách.')
                    ->action(function (ContactMessage $record): void {
                        $record->update([
                            'status' => 'done',
                            'handled_by' => Auth::id(),
                            'handled_at' => now(),
                        ]);

                        Notification::make()->title('Đã đóng tin liên hệ')->success()->send();
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
