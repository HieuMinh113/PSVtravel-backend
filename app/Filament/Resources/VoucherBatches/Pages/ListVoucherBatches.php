<?php

namespace App\Filament\Resources\VoucherBatches\Pages;

use App\Filament\Pages\QuetVoucher;
use App\Filament\Resources\VoucherBatches\VoucherBatchResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListVoucherBatches extends ListRecords
{
    protected static string $resource = VoucherBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('quet')->label('Quét voucher')->icon(Heroicon::OutlinedQrCode)->color('gray')
                ->url(fn () => QuetVoucher::getUrl())->visible(fn () => QuetVoucher::canAccess()),
            CreateAction::make(),
        ];
    }
}
