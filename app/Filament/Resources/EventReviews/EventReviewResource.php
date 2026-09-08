<?php

namespace App\Filament\Resources\EventReviews;

use App\Filament\Resources\EventReviews\Pages\EditEventReview;
use App\Filament\Resources\EventReviews\Pages\ListEventReviews;
use App\Filament\Resources\EventReviews\Pages\ViewEventReview;
use App\Filament\Resources\EventReviews\Schemas\EventReviewForm;
use App\Filament\Resources\EventReviews\Tables\EventReviewsTable;
use App\Models\EventReview;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EventReviewResource extends Resource
{
    protected static ?string $model = EventReview::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?string $recordTitleAttribute = 'customer_name';
    protected static ?string $modelLabel = 'đánh giá sự kiện';
    protected static ?string $pluralModelLabel = 'Đánh giá sự kiện';
    protected static string|\UnitEnum|null $navigationGroup = 'Nội dung';
    protected static ?int $navigationSort = 26;

    public static function form(Schema $schema): Schema
    {
        return EventReviewForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EventReviewsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    // Số đánh giá đang chờ duyệt hiện ngay trên menu
    public static function getNavigationBadge(): ?string
    {
        $cho = static::getModel()::where('status', 'pending')->count();

        return $cho > 0 ? (string) $cho : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEventReviews::route('/'),
            'view' => ViewEventReview::route('/{record}'),
            'edit' => EditEventReview::route('/{record}/edit'),
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
