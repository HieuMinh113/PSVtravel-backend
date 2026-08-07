<?php

namespace App\Filament\Resources\Activities;

use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Filament\Resources\Activities\Pages\ViewActivity;
use App\Filament\Resources\Activities\Schemas\ActivityInfolist;
use App\Filament\Resources\Activities\Tables\ActivitiesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $recordTitleAttribute = 'description';

    protected static ?string $modelLabel = 'nhật ký';

    protected static ?string $pluralModelLabel = 'Nhật ký hoạt động';

    protected static string|\UnitEnum|null $navigationGroup = 'Hệ thống';

    protected static ?int $navigationSort = 95;

    // Nhật ký chỉ để đọc — không ai được tạo thêm bản ghi thủ công
    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return ActivityInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ActivitiesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
            'view' => ViewActivity::route('/{record}'),
        ];
    }

    // Nhãn tiếng Việt cho từng loại đối tượng bị tác động
    public static function nhanDoiTuong(?string $class): string
    {
        return match ($class) {
            \Modules\Tour\Models\Tour::class => 'Tour',
            \Modules\Tour\Models\TourDeparture::class => 'Đợt khởi hành',
            \Modules\Booking\Models\Booking::class => 'Đơn đặt tour',
            \App\Models\User::class => 'Người dùng',
            default => class_basename((string) $class),
        };
    }

    public static function nhanHanhDong(?string $event): string
    {
        return match ($event) {
            'created' => 'Tạo mới',
            'updated' => 'Cập nhật',
            'deleted' => 'Xoá',
            'restored' => 'Khôi phục',
            default => (string) $event,
        };
    }
}