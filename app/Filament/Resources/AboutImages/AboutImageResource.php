<?php

namespace App\Filament\Resources\AboutImages;

use App\Filament\Resources\AboutImages\Pages\CreateAboutImage;
use App\Filament\Resources\AboutImages\Pages\EditAboutImage;
use App\Filament\Resources\AboutImages\Pages\ListAboutImages;
use App\Filament\Resources\AboutImages\Schemas\AboutImageForm;
use App\Filament\Resources\AboutImages\Tables\AboutImagesTable;
use App\Models\AboutImage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AboutImageResource extends Resource
{
    protected static ?string $model = AboutImage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $recordTitleAttribute = 'caption';
    protected static ?string $modelLabel = 'hình ảnh';
    protected static ?string $pluralModelLabel = 'Hình ảnh Về chúng tôi';
    protected static string|\UnitEnum|null $navigationGroup = 'Nội dung';
    protected static ?int $navigationSort = 45;

    public static function form(Schema $schema): Schema
    {
        return AboutImageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AboutImagesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAboutImages::route('/'),
            'create' => CreateAboutImage::route('/create'),
            'edit' => EditAboutImage::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
