<?php

namespace App\Filament\Resources\VisaChecklists\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Visa\Models\VisaCase;
use Modules\Visa\Models\VisaChecklist;

class VisaChecklistsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Mẫu')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('country')
                    ->label('Nước')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('purpose')
                    ->label('Mục đích')
                    ->formatStateUsing(fn (?string $state) => VisaCase::MUC_DICH[$state] ?? 'Chung')
                    ->placeholder('Chung'),
                TextColumn::make('profile')
                    ->label('Đối tượng')
                    ->formatStateUsing(fn (?string $state) => VisaCase::DOI_TUONG[$state] ?? 'Chung')
                    ->placeholder('Chung'),
                TextColumn::make('so_giay')
                    ->label('Số giấy tờ')
                    ->state(fn (VisaChecklist $record) => count($record->items ?? [])),
                ToggleColumn::make('is_active')
                    ->label('Đang dùng'),
            ])
            ->defaultSort('sort_order')
            ->filters([
                SelectFilter::make('country')
                    ->label('Nước')
                    ->options(fn () => VisaChecklist::query()->whereNotNull('country')->distinct()->orderBy('country')->pluck('country', 'country')->all()),
            ])
            ->recordActions([
                // Nhân bản để làm biến thể (cùng nước, khác đối tượng) cho nhanh
                ReplicateAction::make()
                    ->label('Nhân bản')
                    ->mutateRecordDataUsing(function (array $data): array {
                        $data['name'] .= ' (bản sao)';
                        unset($data['attachments'], $data['attachment_names']);

                        return $data;
                    })
                    ->excludeAttributes(['attachments', 'attachment_names']),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
