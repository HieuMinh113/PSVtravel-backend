<?php

namespace App\Filament\Resources\Moments;

use App\Filament\Resources\Moments\Pages\CreateMoment;
use App\Filament\Resources\Moments\Pages\EditMoment;
use App\Filament\Resources\Moments\Pages\ListMoments;
use App\Filament\Resources\Moments\Pages\ViewMoment;
use App\Filament\Resources\Moments\Schemas\MomentForm;
use App\Filament\Resources\Moments\Schemas\MomentInfolist;
use App\Filament\Resources\Moments\Tables\MomentsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\Moment\Models\Moment;

class MomentResource extends Resource
{
    protected static ?string $model = Moment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCamera;
    protected static ?string $recordTitleAttribute = 'caption';
    protected static ?string $modelLabel = 'khoảnh khắc';
    protected static ?string $pluralModelLabel = 'Khoảnh khắc du khách';
    protected static string|\UnitEnum|null $navigationGroup = 'Nội dung';
    protected static ?int $navigationSort = 40;
    public static function form(Schema $schema): Schema
    {
        return MomentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MomentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MomentsTable::configure($table);
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
            'index' => ListMoments::route('/'),
            'create' => CreateMoment::route('/create'),
            'view' => ViewMoment::route('/{record}'),
            'edit' => EditMoment::route('/{record}/edit'),
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
