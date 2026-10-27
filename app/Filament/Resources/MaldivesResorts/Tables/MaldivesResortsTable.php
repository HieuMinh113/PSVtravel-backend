<?php

namespace App\Filament\Resources\MaldivesResorts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MaldivesResortsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['roomTypes', 'combos']))
            ->columns([
                ImageColumn::make('cover_image')->label('Ảnh')->disk('public'),
                TextColumn::make('name')->label('Resort')->searchable()->description(fn ($record) => collect([$record->saoChu(), $record->atoll])->filter()->implode(' · ')),
                TextColumn::make('room_types_count')->label('Hạng phòng')->badge()->color(fn (int $state) => $state ? 'info' : 'danger'),
                TextColumn::make('combos_count')->label('Combo')->badge()->color('gray'),
                TextColumn::make('status')->label('Trạng thái')->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'published' ? 'Đang hiện' : 'Tạm ẩn')
                    ->color(fn (string $state) => $state === 'published' ? 'success' : 'gray'),
                TextColumn::make('sort_order')->label('Thứ tự')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([TrashedFilter::make()->label('Đã xoá')])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make(), RestoreBulkAction::make()])]);
    }
}
