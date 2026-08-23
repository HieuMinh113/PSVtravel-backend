<?php

namespace App\Filament\Resources\Tours\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ToursTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('cover_image')
                    ->label('Ảnh'),
                TextColumn::make('name')
                    ->label('Tên tour')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Loại')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'abroad' ? 'Nước ngoài' : 'Trong nước'),
                TextColumn::make('adult_price')
                    ->label('Giá người lớn')
                    ->money('VND')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'published' => 'Đang bán',
                        'hidden' => 'Đã ẩn',
                        default => 'Nháp',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'hidden' => 'warning',
                        default => 'gray',
                    }),
                IconColumn::make('is_featured')
                    ->label('Nổi bật')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label('Thứ tự')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'draft' => 'Nháp',
                        'published' => 'Đang bán',
                        'hidden' => 'Đã ẩn',
                    ]),
                TrashedFilter::make()
                    ->label('Đã xoá'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    // Không cho xoá vĩnh viễn hàng loạt: chọn nhầm một tour
                    // đang có đơn là mất trắng lịch sử giao dịch. Muốn xoá hẳn
                    // thì mở từng tour, ở đó có kiểm tra đơn trước khi cho xoá.
                    ForceDeleteBulkAction::make()
                        ->visible(false),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}