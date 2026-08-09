<?php

namespace App\Filament\Resources\VisaProviders\Tables;

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

class VisaProvidersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Tên đối tác')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('contact_person')
                    ->label('Người liên hệ')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Điện thoại')
                    ->placeholder('—')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->placeholder('—')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'active' ? 'Đang hợp tác' : 'Ngừng hợp tác')
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray'),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'active' => 'Đang hợp tác',
                        'inactive' => 'Ngừng hợp tác',
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