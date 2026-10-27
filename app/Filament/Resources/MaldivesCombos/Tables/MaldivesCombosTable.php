<?php

namespace App\Filament\Resources\MaldivesCombos\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Maldives\Models\MaldivesCombo;

class MaldivesCombosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['resort:id,name', 'dates']))
            ->columns([
                TextColumn::make('name')->label('Combo')->searchable()->wrap()
                    ->description(fn (MaldivesCombo $r) => collect([$r->resort?->name, $r->duration_days ? "{$r->duration_days}N{$r->duration_nights}Đ" : null, $r->airline])->filter()->implode(' · ')),
                TextColumn::make('gia_tu')->label('Giá từ')
                    ->state(fn (MaldivesCombo $r): ?string => ($g = $r->giaTu()) ? '$'.number_format($g, 0, ',', '.') : null)
                    ->placeholder('Chưa có giá'),
                // Ngày sắp tới còn nhận khách — 0 thì combo hiện trên web nhưng không đặt được
                TextColumn::make('ngay_sap_toi')->label('Ngày sắp tới')
                    ->state(fn (MaldivesCombo $r): int => $r->dates->filter(fn ($d) => $d->status === 'open' && ! $d->start_date->lt(today()))->count())
                    ->badge()->color(fn (int $state) => $state ? 'success' : 'danger'),
                TextColumn::make('status')->label('Trạng thái')->badge()
                    ->formatStateUsing(fn (string $state) => MaldivesCombo::TRANG_THAI[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'published' => 'success', 'draft' => 'warning', default => 'gray'
                    }),
                TextColumn::make('sort_order')->label('Thứ tự')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                SelectFilter::make('status')->label('Trạng thái')->options(MaldivesCombo::TRANG_THAI),
                SelectFilter::make('resort_id')->label('Resort')->relationship('resort', 'name'),
                TrashedFilter::make()->label('Đã xoá'),
            ])
            ->recordActions([
                Action::make('xem')->label('Xem')->icon('heroicon-o-eye')
                    ->url(fn (MaldivesCombo $r): string => rtrim((string) config('app.frontend_url', 'https://psvtravel.com'), '/').'/maldives/'.$r->slug)
                    ->openUrlInNewTab()->visible(fn (MaldivesCombo $r) => $r->status === 'published'),
                EditAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make(), RestoreBulkAction::make()])]);
    }
}
