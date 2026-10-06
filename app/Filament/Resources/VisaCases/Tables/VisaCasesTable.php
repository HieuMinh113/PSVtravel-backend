<?php

namespace App\Filament\Resources\VisaCases\Tables;

use App\Filament\Resources\VisaCases\Actions\TinNhanGiayThieuAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Visa\Models\VisaCase;

class VisaCasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label('Khách')
                    ->searchable(['full_name', 'code', 'phone', 'passport_no', 'group_name'])
                    ->weight('bold')
                    ->description(fn (VisaCase $record) => collect([$record->code, $record->phone, $record->group_name])->filter()->implode(' · ')),
                TextColumn::make('country')
                    ->label('Nước')
                    ->sortable()
                    ->description(fn (VisaCase $record) => collect([
                        VisaCase::MUC_DICH[$record->purpose] ?? null,
                        VisaCase::DOI_TUONG[$record->profile] ?? null,
                    ])->filter()->implode(' · ')),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => VisaCase::TRANG_THAI[$state] ?? $state)
                    ->color(fn (string $state) => VisaCase::MAU_TRANG_THAI[$state] ?? 'gray'),
                TextColumn::make('giay_to')
                    ->label('Giấy tờ')
                    ->badge()
                    ->state(function (VisaCase $record) {
                        [$co, $can] = $record->tienDoGiayTo();

                        return $can ? "{$co}/{$can}" : '—';
                    })
                    ->color(function (VisaCase $record) {
                        [$co, $can] = $record->tienDoGiayTo();

                        return match (true) {
                            $can === 0 => 'gray',
                            $co >= $can => 'success',
                            default => 'warning',
                        };
                    })
                    ->tooltip(fn (VisaCase $record) => $record->giayConThieu() ? 'Còn thiếu: '.implode('; ', $record->giayConThieu()) : null),
                TextColumn::make('appointment_at')
                    ->label('Lịch hẹn')
                    ->dateTime('H:i d/m/Y')
                    ->sortable()
                    ->placeholder('—')
                    ->color(fn (VisaCase $record) => $record->appointment_at
                        && in_array($record->status, VisaCase::DANG_XU_LY, true)
                        && $record->appointment_at->between(now()->startOfDay(), now()->addDays(3)->endOfDay()) ? 'warning' : null),
                TextColumn::make('travel_date')
                    ->label('Ngày đi')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('—')
                    ->description(fn (VisaCase $record) => $record->hoChieuSapHetHan() ? 'Hộ chiếu < 6 tháng' : null),
                TextColumn::make('nguoiPhuTrach.name')
                    ->label('Phụ trách')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('fee')
                    ->label('Thu khách')
                    ->money('VND')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('con_thu')
                    ->label('Còn phải thu')
                    ->state(fn (VisaCase $record) => $record->conPhaiThu())
                    ->money('VND')
                    ->color(fn ($state) => $state > 0 ? 'danger' : null)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Cập nhật')
                    ->since()
                    ->dateTimeTooltip('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(VisaCase::TRANG_THAI)
                    ->multiple(),
                SelectFilter::make('country')
                    ->label('Nước')
                    ->options(fn () => VisaCase::query()->distinct()->orderBy('country')->pluck('country', 'country')->all())
                    ->searchable(),
                SelectFilter::make('purpose')
                    ->label('Mục đích')
                    ->options(VisaCase::MUC_DICH),
                TernaryFilter::make('cua_toi')
                    ->label('Người phụ trách')
                    ->placeholder('Tất cả')
                    ->trueLabel('Hồ sơ của tôi')
                    ->falseLabel('Của người khác')
                    ->queries(
                        true: fn (Builder $query) => $query->where('assigned_to', auth()->id()),
                        false: fn (Builder $query) => $query->where(fn ($q) => $q->where('assigned_to', '!=', auth()->id())->orWhereNull('assigned_to')),
                    ),
                Filter::make('sap_hen')
                    ->label('Có lịch hẹn trong 7 ngày tới')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereBetween('appointment_at', [now()->startOfDay(), now()->addDays(7)->endOfDay()])),
                Filter::make('con_no')
                    ->label('Khách còn nợ tiền')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereColumn('paid', '<', 'fee')),
            ])
            ->recordActions([
                TinNhanGiayThieuAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
