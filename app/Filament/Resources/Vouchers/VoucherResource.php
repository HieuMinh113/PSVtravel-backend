<?php

namespace App\Filament\Resources\Vouchers;

use App\Filament\Resources\Vouchers\Pages\ListVouchers;
use App\Filament\Resources\Vouchers\Tables\VouchersTable;
use App\Models\Voucher;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Mọi mã voucher (tra mã, xem ai quét lúc nào, huỷ mã mất / hỏng). Mã chỉ sinh
 * từ đợt voucher — không tạo / sửa tay ở đây.
 */
class VoucherResource extends Resource
{
    protected static ?string $model = Voucher::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $recordTitleAttribute = 'code';

    protected static ?string $modelLabel = 'mã voucher';

    protected static ?string $pluralModelLabel = 'Mã voucher';

    protected static string|\UnitEnum|null $navigationGroup = 'Voucher';

    protected static ?int $navigationSort = 30;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return VouchersTable::configure($table)->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListVouchers::route('/')];
    }
}
