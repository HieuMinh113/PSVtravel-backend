<?php

namespace App\Filament\Resources\VisaCountries\Tables;

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

class VisaCountriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('flag_image')->label('Cờ'),
                TextColumn::make('name')
                    ->label('Quốc gia')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('visa_type')
                    ->label('Loại visa')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'business' => 'Công tác',
                        'study' => 'Du học',
                        default => 'Du lịch',
                    }),
                TextColumn::make('price')
                    ->label('Chi phí')
                    ->money('VND')
                    ->sortable(),
                TextColumn::make('processing_time')
                    ->label('Thời gian xử lý')
                    ->placeholder('—'),
                TextColumn::make('success_rate')
                    ->label('Tỷ lệ đậu')
                    ->formatStateUsing(fn (?int $state): string => $state ? $state.'%' : '—')
                    ->sortable(),
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
                SelectFilter::make('visa_type')
                    ->label('Loại visa')
                    ->options([
                        'tourist' => 'Du lịch',
                        'business' => 'Công tác',
                        'study' => 'Du học',
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