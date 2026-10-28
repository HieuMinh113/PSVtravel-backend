<?php

namespace App\Filament\Resources\VoucherBatches;

use App\Filament\Resources\VoucherBatches\Pages\CreateVoucherBatch;
use App\Filament\Resources\VoucherBatches\Pages\EditVoucherBatch;
use App\Filament\Resources\VoucherBatches\Pages\ListVoucherBatches;
use App\Filament\Resources\VoucherBatches\RelationManagers\VouchersRelationManager;
use App\Filament\Resources\VoucherBatches\Schemas\VoucherBatchForm;
use App\Filament\Resources\VoucherBatches\Tables\VoucherBatchesTable;
use App\Models\VoucherBatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Đợt voucher: mệnh giá, hạn dùng, ảnh 2 mặt, vị trí ô mã; tạo mã (1–1000 mỗi
 * lần) và tải file in / ảnh.
 */
class VoucherBatchResource extends Resource
{
    protected static ?string $model = VoucherBatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'đợt voucher';

    protected static ?string $pluralModelLabel = 'Đợt voucher';

    protected static string|\UnitEnum|null $navigationGroup = 'Voucher';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return VoucherBatchForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VoucherBatchesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [VouchersRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVoucherBatches::route('/'),
            'create' => CreateVoucherBatch::route('/create'),
            'edit' => EditVoucherBatch::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
