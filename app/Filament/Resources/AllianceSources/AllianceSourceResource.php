<?php

namespace App\Filament\Resources\AllianceSources;

use App\Filament\Resources\AllianceSources\Pages\CreateAllianceSource;
use App\Filament\Resources\AllianceSources\Pages\EditAllianceSource;
use App\Filament\Resources\AllianceSources\Pages\ListAllianceSources;
use App\Filament\Resources\AllianceSources\Schemas\AllianceSourceForm;
use App\Filament\Resources\AllianceSources\Tables\AllianceSourcesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Alliance\Models\AllianceSource;

/**
 * Danh sách sheet chỗ trống của đối tác liên minh. Điều hành chỉ cần dán link
 * sheet; máy tự đọc lại 10–15 phút/lần và gom vào trang "Tra chỗ liên minh".
 */
class AllianceSourceResource extends Resource
{
    protected static ?string $model = AllianceSource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'sheet liên minh';

    protected static ?string $pluralModelLabel = 'Sheet liên minh';

    protected static ?string $navigationLabel = 'Sheet liên minh';

    protected static string|\UnitEnum|null $navigationGroup = 'Điều hành';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return AllianceSourceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AllianceSourcesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAllianceSources::route('/'),
            'create' => CreateAllianceSource::route('/create'),
            'edit' => EditAllianceSource::route('/{record}/edit'),
        ];
    }

    /** Số sheet đang lỗi hiện cạnh menu để điều hành thấy ngay. */
    public static function getNavigationBadge(): ?string
    {
        $loi = AllianceSource::where('is_active', true)->whereNotNull('last_error')->count();

        return $loi ? (string) $loi : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Sheet đang không đọc được';
    }
}
