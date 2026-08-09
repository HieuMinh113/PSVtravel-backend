<?php

namespace App\Filament\Resources\Airlines\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class AirlinesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo')->label('Logo'),
                TextColumn::make('code')
                    ->label('Mã')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Tên hãng bay')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('country')
                    ->label('Quốc gia')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('deals_count')
                    ->label('Số chặng')
                    ->counts([
                        'deals' => fn ($query) => $query->whereNull('flight_deals.deleted_at'),
                    ])
                    ->badge()
                    ->color('gray'),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'published' ? 'Đang hiển thị' : 'Đã ẩn')
                    ->color(fn (string $state): string => $state === 'published' ? 'success' : 'gray'),
                TextColumn::make('sort_order')
                    ->label('Thứ tự')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'published' => 'Đang hiển thị',
                        'hidden' => 'Đã ẩn',
                    ]),
                TrashedFilter::make()->label('Đã xoá'),
            ])
            ->recordActions([
                ViewAction::make(),
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