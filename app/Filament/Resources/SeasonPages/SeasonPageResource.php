<?php

namespace App\Filament\Resources\SeasonPages;

use App\Filament\Resources\SeasonPages\Pages\CreateSeasonPage;
use App\Filament\Resources\SeasonPages\Pages\EditSeasonPage;
use App\Filament\Resources\SeasonPages\Pages\ListSeasonPages;
use App\Filament\Resources\SeasonPages\Schemas\SeasonPageForm;
use App\Filament\Resources\SeasonPages\Tables\SeasonPagesTable;
use App\Models\SeasonPage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SeasonPageResource extends Resource
{
    protected static ?string $model = SeasonPage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSun;

    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $modelLabel = 'trang theo mùa/dịp';
    protected static ?string $pluralModelLabel = 'Trang theo mùa/dịp';
    protected static string|\UnitEnum|null $navigationGroup = 'Nội dung';
    protected static ?int $navigationSort = 36;

    public static function form(Schema $schema): Schema
    {
        return SeasonPageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SeasonPagesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSeasonPages::route('/'),
            'create' => CreateSeasonPage::route('/create'),
            'edit' => EditSeasonPage::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
