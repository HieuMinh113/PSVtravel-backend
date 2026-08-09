<?php

namespace App\Filament\Resources\Activities\Tables;

use App\Filament\Resources\Activities\ActivityResource;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

class ActivitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Thời điểm')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                TextColumn::make('causer.name')
                    ->label('Người thực hiện')
                    ->placeholder('Hệ thống'),
                TextColumn::make('event')
                    ->label('Hành động')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ActivityResource::nhanHanhDong($state))
                    ->color(fn (?string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'info',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('subject_type')
                    ->label('Đối tượng')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state): string => ActivityResource::nhanDoiTuong($state)),
                TextColumn::make('subject_id')
                    ->label('Mã bản ghi')
                    ->placeholder('—'),
                TextColumn::make('log_name')
                    ->label('Nhóm')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('event')
                    ->label('Hành động')
                    ->options([
                        'created' => 'Tạo mới',
                        'updated' => 'Cập nhật',
                        'deleted' => 'Xoá',
                    ]),
                SelectFilter::make('log_name')
                    ->label('Đối tượng')
                    ->options([
                        'tour' => 'Tour',
                        'tour_departure' => 'Đợt khởi hành',
                        'booking' => 'Đơn đặt tour',
                        'user' => 'Người dùng',
                    ]),
                SelectFilter::make('causer_id')
                    ->label('Người thực hiện')
                    ->options(fn (): array => \App\Models\User::query()
                        ->whereIn('id', Activity::query()->whereNotNull('causer_id')->distinct()->pluck('causer_id'))
                        ->pluck('name', 'id')
                        ->toArray()),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}