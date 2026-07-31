<?php

namespace App\Filament\Resources\Tours\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    protected static ?string $title = 'Thư viện ảnh';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('path')
                ->label('Ảnh')
                ->image()
                ->directory('tours/gallery')
                ->required(),
            TextInput::make('alt')
                ->label('Mô tả ảnh (alt)'),
            Toggle::make('is_cover')
                ->label('Ảnh bìa'),
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
                ImageColumn::make('path')->label('Ảnh'),
                TextColumn::make('alt')->label('Mô tả')->placeholder('—'),
                IconColumn::make('is_cover')->label('Ảnh bìa')->boolean(),
                TextColumn::make('sort_order')->label('Thứ tự')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->headerActions([
                CreateAction::make()->label('Thêm ảnh'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}