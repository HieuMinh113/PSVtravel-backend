<?php

namespace App\Filament\Resources\FlightDeals;

use App\Filament\Resources\FlightDeals\Pages\CreateFlightDeal;
use App\Filament\Resources\FlightDeals\Pages\EditFlightDeal;
use App\Filament\Resources\FlightDeals\Pages\ListFlightDeals;
use App\Filament\Resources\FlightDeals\Pages\ViewFlightDeal;
use App\Filament\Resources\FlightDeals\Schemas\FlightDealForm;
use App\Filament\Resources\FlightDeals\Schemas\FlightDealInfolist;
use App\Filament\Resources\FlightDeals\Tables\FlightDealsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\Flight\Models\FlightDeal;

class FlightDealResource extends Resource
{
    protected static ?string $model = FlightDeal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;
    protected static ?string $recordTitleAttribute = 'to_city';
    protected static ?string $modelLabel = 'chặng bay';
    protected static ?string $pluralModelLabel = 'Chặng bay ưu đãi';
    protected static string|\UnitEnum|null $navigationGroup = 'Vé máy bay';
    protected static ?int $navigationSort = 20; 

    public static function form(Schema $schema): Schema
    {
        return FlightDealForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FlightDealInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FlightDealsTable::configure($table);
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
            'index' => ListFlightDeals::route('/'),
            'create' => CreateFlightDeal::route('/create'),
            'view' => ViewFlightDeal::route('/{record}'),
            'edit' => EditFlightDeal::route('/{record}/edit'),
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
