<?php

namespace App\Filament\Resources\Vouchers\Pages;

use App\Filament\Pages\QuetVoucher;
use App\Filament\Resources\Vouchers\VoucherResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListVouchers extends ListRecords
{
    protected static string $resource = VoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('quet')->label('Quét voucher')->icon(Heroicon::OutlinedQrCode)
                ->url(fn () => QuetVoucher::getUrl())->visible(fn () => QuetVoucher::canAccess()),
        ];
    }
}
