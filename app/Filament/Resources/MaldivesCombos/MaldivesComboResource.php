<?php

namespace App\Filament\Resources\MaldivesCombos;

use App\Filament\Resources\MaldivesCombos\Pages\CreateMaldivesCombo;
use App\Filament\Resources\MaldivesCombos\Pages\EditMaldivesCombo;
use App\Filament\Resources\MaldivesCombos\Pages\ListMaldivesCombos;
use App\Filament\Resources\MaldivesCombos\Schemas\MaldivesComboForm;
use App\Filament\Resources\MaldivesCombos\Tables\MaldivesCombosTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\Maldives\Models\MaldivesCombo;

class MaldivesComboResource extends Resource
{
    protected static ?string $model = MaldivesCombo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSun;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'combo Maldives';

    protected static ?string $pluralModelLabel = 'Combo Maldives';

    protected static string|\UnitEnum|null $navigationGroup = 'Maldives';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return MaldivesComboForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MaldivesCombosTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaldivesCombos::route('/'),
            'create' => CreateMaldivesCombo::route('/create'),
            'edit' => EditMaldivesCombo::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
