<?php

namespace App\Filament\Resources\Settings\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Page\Models\Setting;

class SettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->label('Tên cấu hình')
                    ->searchable(),
                TextColumn::make('value')
                    ->label('Giá trị hiện tại')
                    ->placeholder('Chưa đặt')
                    ->limit(60)
                    ->wrap()
                    ->tooltip(fn (Setting $record): ?string => $record->value),
                TextColumn::make('group')
                    ->label('Nhóm')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'contact' => 'Liên hệ',
                        'social' => 'Mạng xã hội',
                        'seo' => 'SEO',
                        'legal' => 'Pháp lý',
                        default => 'Chung',
                    }),
            ])
            ->defaultGroup('group')
            ->defaultSort('sort_order')
            ->paginated(false)
            ->filters([
                SelectFilter::make('group')
                    ->label('Nhóm')
                    ->options([
                        'general' => 'Chung',
                        'contact' => 'Liên hệ',
                        'social' => 'Mạng xã hội',
                        'seo' => 'SEO',
                        'legal' => 'Pháp lý',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}