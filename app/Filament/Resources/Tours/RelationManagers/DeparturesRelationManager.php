<?php

namespace App\Filament\Resources\Tours\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DeparturesRelationManager extends RelationManager
{
    protected static string $relationship = 'departures';

    protected static ?string $title = 'Lịch khởi hành';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('start_date')
                ->label('Ngày khởi hành')
                ->required(),
            TextInput::make('price_override')
                ->label('Giá riêng đợt này')
                ->helperText('Để trống nếu dùng giá mặc định của tour')
                ->numeric()
                ->suffix('₫'),
            TextInput::make('seats_total')
                ->label('Tổng số chỗ')
                ->numeric()
                ->default(0)
                ->required(),
            TextInput::make('seats_left')
                ->label('Số chỗ còn')
                ->numeric()
                ->default(0)
                ->required(),
            Select::make('status')
                ->label('Trạng thái')
                ->options([
                    'open' => 'Còn nhận',
                    'closed' => 'Đã đóng',
                    'full' => 'Hết chỗ',
                ])
                ->default('open')
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('start_date')
                    ->label('Ngày khởi hành')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('price_override')
                    ->label('Giá riêng')
                    ->money('VND')
                    ->placeholder('—'),
                TextColumn::make('seats_total')->label('Tổng chỗ'),
                TextColumn::make('seats_left')->label('Còn'),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'closed' => 'Đã đóng',
                        'full' => 'Hết chỗ',
                        default => 'Còn nhận',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'closed' => 'gray',
                        'full' => 'danger',
                        default => 'success',
                    }),
            ])
            ->defaultSort('start_date')
            ->headerActions([
                CreateAction::make()->label('Thêm đợt khởi hành'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}