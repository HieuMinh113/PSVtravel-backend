<?php

namespace App\Filament\Resources\VisaCases;

use App\Filament\Resources\VisaCases\Pages\CreateVisaCase;
use App\Filament\Resources\VisaCases\Pages\EditVisaCase;
use App\Filament\Resources\VisaCases\Pages\ListVisaCases;
use App\Filament\Resources\VisaCases\Schemas\VisaCaseForm;
use App\Filament\Resources\VisaCases\Tables\VisaCasesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Visa\Models\VisaCase;

/**
 * Hồ sơ visa theo từng case: thông tin khách, checklist giấy tờ (chép từ mẫu
 * rồi sửa tuỳ case), file scan, lịch hẹn, kết quả, tiền thu / chi.
 */
class VisaCaseResource extends Resource
{
    protected static ?string $model = VisaCase::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $recordTitleAttribute = 'full_name';

    protected static ?string $modelLabel = 'hồ sơ visa';

    protected static ?string $pluralModelLabel = 'Hồ sơ visa';

    protected static ?string $navigationLabel = 'Hồ sơ visa';

    protected static string|\UnitEnum|null $navigationGroup = 'Visa';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return VisaCaseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VisaCasesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVisaCases::route('/'),
            'create' => CreateVisaCase::route('/create'),
            'edit' => EditVisaCase::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'full_name', 'phone', 'passport_no'];
    }

    /** Số hồ sơ có lịch hẹn (nộp / lăn tay / phỏng vấn) trong 3 ngày tới. */
    public static function getNavigationBadge(): ?string
    {
        $so = VisaCase::whereIn('status', VisaCase::DANG_XU_LY)
            ->whereBetween('appointment_at', [now()->startOfDay(), now()->addDays(3)->endOfDay()])
            ->count();

        return $so ? (string) $so : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Hồ sơ có lịch hẹn trong 3 ngày tới';
    }
}
