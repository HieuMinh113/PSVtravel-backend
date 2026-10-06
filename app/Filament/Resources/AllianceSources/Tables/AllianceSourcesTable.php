<?php

namespace App\Filament\Resources\AllianceSources\Tables;

use App\Filament\Resources\AllianceSources\Actions\DocNgayAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Modules\Alliance\Models\AllianceSource;

class AllianceSourcesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Đối tác')
                    ->searchable()
                    // Đang lỗi thì hiện luôn lý do ngay dưới tên, khỏi phải rê chuột
                    ->description(fn (AllianceSource $record) => $record->last_error
                        ? mb_strimwidth($record->last_error, 0, 110, '…')
                        : ($record->contact ? mb_strimwidth($record->contact, 0, 60, '…') : null))
                    ->url(fn (AllianceSource $record) => $record->sheet_url, shouldOpenInNewTab: true),
                TextColumn::make('trang_thai_doc')
                    ->label('Tình trạng')
                    ->badge()
                    ->state(fn (AllianceSource $record) => match (true) {
                        $record->last_error !== null => 'Lỗi',
                        $record->last_success_at === null => 'Chưa đọc',
                        default => 'Bình thường',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'Lỗi' => 'danger',
                        'Chưa đọc' => 'gray',
                        default => 'success',
                    })
                    ->tooltip(fn (AllianceSource $record) => $record->last_error),
                TextColumn::make('departures_count')
                    ->label('Ngày đi sắp tới')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('last_success_at')
                    ->label('Đọc được lúc')
                    ->since()
                    ->dateTimeTooltip('d/m/Y H:i')
                    ->placeholder('—'),
                TextColumn::make('sheet_updated_on')
                    ->label('Sheet ghi cập nhật')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make('is_active')
                    ->label('Theo dõi'),
            ])
            ->defaultSort('name')
            ->recordActions([
                DocNgayAction::make(),
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Xoá sheet này sẽ xoá luôn các tour / ngày đi đã đọc từ nó. Tour PSV đang nối sẽ thôi tự cập nhật số chỗ.'),
            ]);
    }
}
