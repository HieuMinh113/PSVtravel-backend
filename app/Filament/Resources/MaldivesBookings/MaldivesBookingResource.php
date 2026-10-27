<?php

namespace App\Filament\Resources\MaldivesBookings;

use App\Filament\Resources\MaldivesBookings\Pages\CreateMaldivesBooking;
use App\Filament\Resources\MaldivesBookings\Pages\EditMaldivesBooking;
use App\Filament\Resources\MaldivesBookings\Pages\ListMaldivesBookings;
use App\Filament\Resources\MaldivesBookings\Pages\ViewMaldivesBooking;
use App\Filament\Resources\MaldivesBookings\Schemas\MaldivesBookingForm;
use App\Filament\Resources\MaldivesBookings\Schemas\MaldivesBookingInfolist;
use App\Filament\Resources\MaldivesBookings\Tables\MaldivesBookingsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\Maldives\Models\MaldivesBooking;

class MaldivesBookingResource extends Resource
{
    protected static ?string $model = MaldivesBooking::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?string $recordTitleAttribute = 'code';

    protected static ?string $modelLabel = 'đơn Maldives';

    protected static ?string $pluralModelLabel = 'Đơn Maldives';

    protected static string|\UnitEnum|null $navigationGroup = 'Maldives';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return MaldivesBookingForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MaldivesBookingInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MaldivesBookingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaldivesBookings::route('/'),
            'create' => CreateMaldivesBooking::route('/create'),
            'view' => ViewMaldivesBooking::route('/{record}'),
            'edit' => EditMaldivesBooking::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    // Đơn khách đặt web chưa ai nhận — giống số cạnh "Tin liên hệ"
    public static function getNavigationBadge(): ?string
    {
        $so = MaldivesBooking::query()->where('status', 'pending')->count();

        return $so ? (string) $so : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Đơn mới chưa ai xử lý';
    }
}
