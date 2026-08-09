<?php

namespace App\Filament\Resources\VisaProviders;

use App\Filament\Resources\VisaProviders\Pages\CreateVisaProvider;
use App\Filament\Resources\VisaProviders\Pages\EditVisaProvider;
use App\Filament\Resources\VisaProviders\Pages\ListVisaProviders;
use App\Filament\Resources\VisaProviders\Pages\ViewVisaProvider;
use App\Filament\Resources\VisaProviders\Schemas\VisaProviderForm;
use App\Filament\Resources\VisaProviders\Schemas\VisaProviderInfolist;
use App\Filament\Resources\VisaProviders\Tables\VisaProvidersTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\Visa\Models\VisaProvider;

class VisaProviderResource extends Resource
{
    protected static ?string $model = VisaProvider::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $modelLabel = 'đối tác visa';
    protected static ?string $pluralModelLabel = 'Đối tác làm visa';
    protected static string|\UnitEnum|null $navigationGroup = 'Visa';
    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return VisaProviderForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VisaProviderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VisaProvidersTable::configure($table);
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
            'index' => ListVisaProviders::route('/'),
            'create' => CreateVisaProvider::route('/create'),
            'view' => ViewVisaProvider::route('/{record}'),
            'edit' => EditVisaProvider::route('/{record}/edit'),
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
