<?php

namespace App\Filament\Resources\MaldivesResorts;

use App\Filament\Resources\MaldivesResorts\Pages\CreateMaldivesResort;
use App\Filament\Resources\MaldivesResorts\Pages\EditMaldivesResort;
use App\Filament\Resources\MaldivesResorts\Pages\ListMaldivesResorts;
use App\Filament\Resources\MaldivesResorts\Schemas\MaldivesResortForm;
use App\Filament\Resources\MaldivesResorts\Tables\MaldivesResortsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\Maldives\Models\MaldivesResort;

class MaldivesResortResource extends Resource
{
    protected static ?string $model = MaldivesResort::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'resort';

    protected static ?string $pluralModelLabel = 'Resort Maldives';

    protected static string|\UnitEnum|null $navigationGroup = 'Maldives';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return MaldivesResortForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MaldivesResortsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaldivesResorts::route('/'),
            'create' => CreateMaldivesResort::route('/create'),
            'edit' => EditMaldivesResort::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
