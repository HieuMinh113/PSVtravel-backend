<?php

namespace App\Filament\Resources\AllianceDepartures;

use App\Filament\Resources\AllianceDepartures\Pages\ListAllianceDepartures;
use App\Filament\Resources\AllianceDepartures\Tables\AllianceDeparturesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Alliance\Models\AllianceDeparture;

/**
 * Tra chỗ liên minh: gom mọi ngày khởi hành từ các sheet đối tác vào một bảng
 * để điều hành tìm "tour nào, ngày nào, còn mấy chỗ" mà không phải mở từng
 * sheet. Chỉ xem — dữ liệu do máy đọc từ sheet.
 */
class AllianceDepartureResource extends Resource
{
    protected static ?string $model = AllianceDeparture::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlassCircle;

    protected static ?string $modelLabel = 'ngày đi liên minh';

    protected static ?string $pluralModelLabel = 'Tra chỗ liên minh';

    protected static ?string $navigationLabel = 'Tra chỗ liên minh';

    protected static string|\UnitEnum|null $navigationGroup = 'Điều hành';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'tra-cho-lien-minh';

    public static function table(Table $table): Table
    {
        return AllianceDeparturesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        // Ngày đi từ hôm nay, cộng các tour khởi hành "Thứ 5 hằng tuần" (không có ngày)
        return parent::getEloquentQuery()
            ->where(fn (Builder $q) => $q->whereDate('departure_date', '>=', today())->orWhereNull('departure_date'))
            ->with(['tour.source', 'tour.psvTours:id,name,alliance_tour_id']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAllianceDepartures::route('/'),
        ];
    }
}
