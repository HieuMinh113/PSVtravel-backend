<?php

namespace App\Filament\Resources\Tours;

use App\Filament\Resources\Tours\Pages\CreateTour;
use App\Filament\Resources\Tours\Pages\EditTour;
use App\Filament\Resources\Tours\Pages\ListTours;
use App\Filament\Resources\Tours\Pages\ViewTour;
use App\Filament\Resources\Tours\Schemas\TourForm;
use App\Filament\Resources\Tours\Schemas\TourInfolist;
use App\Filament\Resources\Tours\Tables\ToursTable;
use BackedEnum;
use App\Filament\Resources\Tours\RelationManagers;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\Tour\Models\Tour;

class TourResource extends Resource
{
    protected static ?string $model = Tour::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return TourForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TourInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ToursTable::configure($table);
    }

    public static function getRelations(): array
{
    return [
        RelationManagers\DeparturesRelationManager::class,
        RelationManagers\ItinerariesRelationManager::class,
        RelationManagers\ImagesRelationManager::class,
    ];
}

    public static function getPages(): array
    {
        return [
            'index' => ListTours::route('/'),
            'create' => CreateTour::route('/create'),
            'view' => ViewTour::route('/{record}'),
            'edit' => EditTour::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
