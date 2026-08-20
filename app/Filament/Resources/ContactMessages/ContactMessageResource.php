<?php

namespace App\Filament\Resources\ContactMessages;

use App\Filament\Resources\ContactMessages\Pages\EditContactMessage;
use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Filament\Resources\ContactMessages\Pages\ViewContactMessage;
use App\Filament\Resources\ContactMessages\Schemas\ContactMessageForm;
use App\Filament\Resources\ContactMessages\Tables\ContactMessagesTable;
use App\Models\ContactMessage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $modelLabel = 'tin liên hệ';
    protected static ?string $pluralModelLabel = 'Tin liên hệ';
    protected static string|\UnitEnum|null $navigationGroup = 'Kinh doanh';
    protected static ?int $navigationSort = 20;

    // Tin do khách gửi từ website — không ai tạo tay trong admin
    public static function canCreate(): bool
    {
        return false;
    }

    // Số tin chưa xử lý hiện ngay cạnh menu để không ai bỏ sót khách
    public static function getNavigationBadge(): ?string
    {
        $so = static::getModel()::query()->where('status', 'new')->count();

        return $so > 0 ? (string) $so : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return ContactMessageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContactMessagesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactMessages::route('/'),
            'view' => ViewContactMessage::route('/{record}'),
            'edit' => EditContactMessage::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
