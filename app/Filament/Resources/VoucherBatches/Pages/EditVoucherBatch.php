<?php

namespace App\Filament\Resources\VoucherBatches\Pages;

use App\Filament\Resources\VoucherBatches\Actions\VoucherActions;
use App\Filament\Resources\VoucherBatches\VoucherBatchResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditVoucherBatch extends EditRecord
{
    protected static string $resource = VoucherBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            VoucherActions::taoMa(),
            VoucherActions::xuatFile(),
            DeleteAction::make()->modalDescription('Xoá đợt thì mọi mã của đợt báo "Đã huỷ" khi khách kiểm tra. Khôi phục lại được.'),
            RestoreAction::make(),
        ];
    }
}
