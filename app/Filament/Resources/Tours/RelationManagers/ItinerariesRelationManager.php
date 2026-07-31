<?php

namespace App\Filament\Resources\Tours\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItinerariesRelationManager extends RelationManager
{
    protected static string $relationship = 'itineraries';

    protected static ?string $title = 'Lịch trình từng ngày';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('day_number')
                ->label('Ngày thứ')
                ->numeric()
                ->required(),
            TextInput::make('title')
                ->label('Tiêu đề')
                ->required()
                ->columnSpanFull(),
            Textarea::make('description')
                ->label('Nội dung')
                ->rows(4)
                ->columnSpanFull(),
            TextInput::make('sort_order')
                ->label('Thứ tự')
                ->numeric()
                ->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('day_number')
                    ->label('Ngày')
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Tiêu đề')
                    ->wrap(),
            ])
            ->defaultSort('day_number')
            ->headerActions([
                CreateAction::make()->label('Thêm ngày'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}