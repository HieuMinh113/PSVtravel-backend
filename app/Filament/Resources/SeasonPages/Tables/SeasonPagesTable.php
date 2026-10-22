<?php

namespace App\Filament\Resources\SeasonPages\Tables;

use App\Models\SeasonPage;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class SeasonPagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Tên trang')->searchable()->wrap(),
                TextColumn::make('slug')->label('Đường dẫn')->prefix('/')->copyable(),
                TextColumn::make('start_from')
                    ->label('Khởi hành')
                    ->formatStateUsing(fn (SeasonPage $record): string => $record->start_from->format('d/m/Y').' – '.$record->start_to->format('d/m/Y')),
                // Đếm đúng như trang sẽ hiện: 0 tour thì trang trống, cần xem lại khoảng ngày
                TextColumn::make('so_tour')
                    ->label('Tour đang hiện')
                    ->state(fn (SeasonPage $record): int => $record->truyVanTour()->count())
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'danger'),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'published' ? 'Đang hiển thị' : 'Đã ẩn')
                    ->color(fn (string $state): string => $state === 'published' ? 'success' : 'gray'),
                TextColumn::make('sort_order')->label('Thứ tự')->numeric()->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([TrashedFilter::make()->label('Đã xoá')])
            ->recordActions([
                Action::make('xem')
                    ->label('Xem')
                    ->icon('heroicon-o-eye')
                    ->url(fn (SeasonPage $record): string => rtrim((string) config('app.frontend_url', 'https://psvtravel.com'), '/').'/'.$record->slug)
                    ->openUrlInNewTab(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
