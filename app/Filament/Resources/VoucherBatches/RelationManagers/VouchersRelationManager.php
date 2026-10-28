<?php

namespace App\Filament\Resources\VoucherBatches\RelationManagers;

use App\Filament\Resources\Vouchers\Tables\VouchersTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class VouchersRelationManager extends RelationManager
{
    protected static string $relationship = 'vouchers';

    protected static ?string $title = 'Mã voucher';

    protected static ?string $modelLabel = 'mã voucher';

    // Nhân viên chỉ xem đợt (sale, điều hành) vẫn đánh dấu đã dùng được ở đây
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return VouchersTable::configure($table, coCotDot: false)
            ->heading(fn () => 'Mã voucher — '.$this->getOwnerRecord()->vouchers()->count().' mã, đã dùng '
                .$this->getOwnerRecord()->vouchers()->where('status', 'used')->count());
    }
}
