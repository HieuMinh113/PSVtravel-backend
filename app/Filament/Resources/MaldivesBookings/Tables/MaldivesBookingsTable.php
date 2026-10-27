<?php

namespace App\Filament\Resources\MaldivesBookings\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Maldives\Models\MaldivesBooking;

class MaldivesBookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('nguoiPhuTrach:id,name'))
            ->columns([
                TextColumn::make('code')->label('Mã đơn')->searchable()->weight('bold')
                    ->description(fn (MaldivesBooking $r) => $r->created_at?->format('d/m/Y H:i')),
                TextColumn::make('customer_name')->label('Khách')->searchable()
                    ->description(fn (MaldivesBooking $r) => $r->customer_phone),
                TextColumn::make('combo_name')->label('Combo')->wrap()->limit(60)
                    ->description(fn (MaldivesBooking $r) => collect([$r->start_date?->format('d/m/Y'), $r->room_type_name])->filter()->implode(' · ')),
                TextColumn::make('khach')->label('Khách đi')
                    ->state(fn (MaldivesBooking $r) => $r->adults.' NL'.($r->children ? " + {$r->children} TE" : '').($r->infants ? " + {$r->infants} EB" : '')),
                TextColumn::make('total_usd')->label('Tạm tính')
                    ->formatStateUsing(fn ($state) => $state ? '$'.number_format($state, 0, ',', '.') : null)->placeholder('Báo giá sau')
                    ->description(fn (MaldivesBooking $r) => $r->total_vnd ? '≈ '.number_format($r->total_vnd / 1_000_000, 1, ',', '.').' triệu' : null),
                TextColumn::make('status')->label('Trạng thái')->badge()
                    ->formatStateUsing(fn (string $state) => MaldivesBooking::TRANG_THAI[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'danger', 'contacting' => 'warning', 'cancelled' => 'gray', default => 'success'
                    }),
                TextColumn::make('nguoiPhuTrach.name')->label('Phụ trách')->placeholder('Chưa ai nhận'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Trạng thái')->options(MaldivesBooking::TRANG_THAI),
                Filter::make('cua_toi')->label('Đơn tôi phụ trách')->query(fn (Builder $query) => $query->where('assigned_to', auth()->id())),
                Filter::make('chua_ai_nhan')->label('Chưa ai nhận')->query(fn (Builder $query) => $query->whereNull('assigned_to')),
                TrashedFilter::make()->label('Đã xoá'),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()]);
    }
}
