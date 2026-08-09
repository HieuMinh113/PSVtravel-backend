<?php

namespace App\Filament\Resources\VisaCountries;

use App\Filament\Resources\VisaCountries\Pages\CreateVisaCountry;
use App\Filament\Resources\VisaCountries\Pages\EditVisaCountry;
use App\Filament\Resources\VisaCountries\Pages\ListVisaCountries;
use App\Filament\Resources\VisaCountries\Pages\ViewVisaCountry;
use App\Filament\Resources\VisaCountries\Schemas\VisaCountryForm;
use App\Filament\Resources\VisaCountries\Schemas\VisaCountryInfolist;
use App\Filament\Resources\VisaCountries\Tables\VisaCountriesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\Visa\Models\VisaCountry;

class VisaCountryResource extends Resource
{
    protected static ?string $model = VisaCountry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAsiaAustralia;
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $modelLabel = 'quốc gia visa';
    protected static ?string $pluralModelLabel = 'Visa theo quốc gia';
    protected static string|\UnitEnum|null $navigationGroup = 'Visa';
    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return VisaCountryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VisaCountryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VisaCountriesTable::configure($table);
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
            'index' => ListVisaCountries::route('/'),
            'create' => CreateVisaCountry::route('/create'),
            'view' => ViewVisaCountry::route('/{record}'),
            'edit' => EditVisaCountry::route('/{record}/edit'),
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
