<?php

namespace App\Filament\Resources\Redirects\Tables;

use App\Models\Redirect;
use App\Services\LinkCu;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class RedirectsTable
{
    public static function configure(Table $table): Table
    {
        $web = rtrim((string) config('app.frontend_url', 'https://psvtravel.com'), '/');

        return $table
            ->columns([
                TextColumn::make('old_path')
                    ->label('Link cũ')
                    ->searchable()
                    ->copyable()
                    ->wrap(),
                TextColumn::make('status_code')
                    ->label('Xử lý')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => match ($state) {
                        301 => 'Chuyển (301)',
                        410 => 'Đã gỡ (410)',
                        default => 'Chưa khớp',
                    })
                    ->color(fn (int $state): string => match ($state) {
                        301 => 'success',
                        410 => 'gray',
                        default => 'danger',
                    }),
                TextColumn::make('new_path')
                    ->label('Chuyển tới')
                    ->placeholder('—')
                    ->searchable()
                    ->url(fn (Redirect $record): ?string => $record->status_code === 301 && $record->new_path ? $web.$record->new_path : null)
                    ->openUrlInNewTab()
                    ->wrap(),
                TextColumn::make('source')
                    ->label('Người đặt')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Redirect::NGUON[$state] ?? $state)
                    ->color(fn (string $state): string => $state === 'tay' ? 'info' : 'gray'),
                TextColumn::make('hits')->label('Lượt mở')->numeric()->sortable(),
                TextColumn::make('last_hit_at')->label('Lần cuối')->since()->sortable()->placeholder('—'),
                TextColumn::make('note')->label('Ghi chú')->limit(40)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('hits', 'desc')
            ->filters([
                SelectFilter::make('status_code')->label('Xử lý')->options(Redirect::MA),
                SelectFilter::make('source')->label('Người đặt')->options(Redirect::NGUON),
            ])
            ->recordActions([
                // Mở link cũ trên website thật để thử khách sẽ thấy gì
                Action::make('thu')
                    ->label('Thử')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Redirect $record): string => $web.$record->old_path)
                    ->openUrlInNewTab(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('khopLai')
                        ->label('Tự khớp lại')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->authorize(fn (): bool => auth()->user()?->can('create', Redirect::class) ?? false)
                        ->requiresConfirmation()
                        ->modalDescription('Máy dò lại trang mới cho các dòng đã chọn. Dòng nhân viên đã sửa giữ nguyên.')
                        ->action(function (Collection $records): void {
                            $records->each(fn (Redirect $r) => LinkCu::khopLai($r));
                            Notification::make()->success()->title('Đã khớp lại '.$records->where('source', 'tu_dong')->count().' link')->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Chưa có link cũ nào')
            ->emptyStateDescription('Khi có người mở link của website cũ, hệ thống tự ghi vào đây.');
    }
}
