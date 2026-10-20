<?php

namespace App\Filament\Resources\VisaDeadlines\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Modules\Tour\Services\HanNopVisa;
use Modules\Visa\Models\VisaDeadline;

class VisaDeadlinesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('country')
                    ->label('Quốc gia')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('days')
                    ->label('Nộp visa trước ngày bay')
                    ->formatStateUsing(fn (int $state) => "{$state} ngày")
                    ->description(fn (VisaDeadline $record) => 'VD bay 22/11 → ẩn trên web từ '.Carbon::create(2026, 11, 22)->subDays($record->days)->format('d/m'))
                    ->sortable(),
                TextColumn::make('tour_ap_dung')
                    ->label('Tour áp dụng')
                    ->state(fn (VisaDeadline $record) => count(HanNopVisa::tourTheoNuoc($record->country)))
                    ->tooltip(fn (VisaDeadline $record) => implode(', ', HanNopVisa::tourTheoNuoc($record->country)) ?: 'Chưa có tour nào ghi quốc gia này'),
                TextColumn::make('note')
                    ->label('Ghi chú')
                    ->wrap()
                    ->placeholder('—'),
                TextColumn::make('updated_at')
                    ->label('Cập nhật')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('country')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Chưa có nước nào')
            ->emptyStateDescription('Thêm nước và số ngày nộp visa — ngày khởi hành quá hạn sẽ tự ẩn trên website.');
    }
}
