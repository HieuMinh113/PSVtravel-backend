<?php

namespace App\Filament\Resources\FlightDeals\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Modules\Flight\Models\FlightDeal;

class FlightDealsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('airline.name')
                    ->label('Hãng bay')
                    ->searchable(),
                TextColumn::make('chang')
                    ->label('Chặng bay')
                    ->state(fn (FlightDeal $record): string => $record->from_city.' → '.$record->to_city),
                TextColumn::make('trip_type')
                    ->label('Loại vé')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state): string => $state === 'round_trip' ? 'Khứ hồi' : 'Một chiều'),
                TextColumn::make('price')
                    ->label('Giá tham khảo')
                    ->money('VND')
                    ->sortable(),
                TextColumn::make('valid_to')
                    ->label('Hạn đến')
                    ->date('d/m/Y')
                    ->placeholder('Không giới hạn')
                    ->sortable(),
                TextColumn::make('hien_thi')
                    ->label('Thực tế')
                    ->badge()
                    ->state(function (FlightDeal $record): string {
                        if ($record->status !== 'published') {
                            return 'Đã ẩn';
                        }
                        if ($record->valid_from && $record->valid_from->isFuture()) {
                            return 'Chờ tới hạn';
                        }
                        if ($record->valid_to && $record->valid_to->isPast()) {
                            return 'Đã hết hạn';
                        }

                        return 'Đang chạy';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Đang chạy' => 'success',
                        'Chờ tới hạn' => 'info',
                        'Đã hết hạn' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('sort_order')
                    ->label('Thứ tự')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                SelectFilter::make('airline_id')
                    ->label('Hãng bay')
                    ->relationship('airline', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('trip_type')
                    ->label('Loại vé')
                    ->options([
                        'one_way' => 'Một chiều',
                        'round_trip' => 'Khứ hồi',
                    ]),
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