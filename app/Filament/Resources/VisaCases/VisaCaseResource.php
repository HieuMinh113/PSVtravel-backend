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
use Illuminate\Database\Eloquent\Builder;
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

    /** Nhân viên visa chỉ thấy hồ sơ mình phụ trách + hồ sơ chưa ai nhận. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->nhinThayBoi(auth()->user());
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'full_name', 'phone', 'passport_no'];
    }

    /**
     * Số cạnh menu: hồ sơ khách mới nộp chưa ai nhận (đỏ) — việc gấp nhất;
     * không có thì số hồ sơ có lịch hẹn trong 3 ngày tới (vàng).
     */
    public static function getNavigationBadge(): ?string
    {
        [$so] = self::huyHieu();

        return $so ? (string) $so : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return self::huyHieu()[1] === 'chua_nhan' ? 'danger' : 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return self::huyHieu()[1] === 'chua_nhan' ? 'Hồ sơ khách nộp chưa ai nhận' : 'Hồ sơ có lịch hẹn trong 3 ngày tới';
    }

    /** @return array{0:int,1:string} */
    private static function huyHieu(): array
    {
        static $daTinh = [];
        $khoa = (string) auth()->id();
        if (isset($daTinh[$khoa])) {
            return $daTinh[$khoa];
        }

        $chuaNhan = VisaCase::whereNull('assigned_to')->whereIn('status', VisaCase::DANG_XU_LY)->count();
        if ($chuaNhan) {
            return $daTinh[$khoa] = [$chuaNhan, 'chua_nhan'];
        }

        $sapHen = static::getEloquentQuery()->whereIn('status', VisaCase::DANG_XU_LY)
            ->whereBetween('appointment_at', [now()->startOfDay(), now()->addDays(3)->endOfDay()])
            ->count();

        return $daTinh[$khoa] = [$sapHen, 'sap_hen'];
    }
}
