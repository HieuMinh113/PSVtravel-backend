<?php

namespace App\Filament\Resources\Airlines;

use App\Filament\Resources\Airlines\Pages\CreateAirline;
use App\Filament\Resources\Airlines\Pages\EditAirline;
use App\Filament\Resources\Airlines\Pages\ListAirlines;
use App\Filament\Resources\Airlines\Pages\ViewAirline;
use App\Filament\Resources\Airlines\Schemas\AirlineForm;
use App\Filament\Resources\Airlines\Schemas\AirlineInfolist;
use App\Filament\Resources\Airlines\Tables\AirlinesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\Flight\Models\Airline;

class AirlineResource extends Resource
{
    protected static ?string $model = Airline::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $modelLabel = 'hãng bay';
    protected static ?string $pluralModelLabel = 'Hãng bay';
    protected static string|\UnitEnum|null $navigationGroup = 'Vé máy bay';
    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return AirlineForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AirlineInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AirlinesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAirlines::route('/'),
            'create' => CreateAirline::route('/create'),
            'view' => ViewAirline::route('/{record}'),
            'edit' => EditAirline::route('/{record}/edit'),
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
