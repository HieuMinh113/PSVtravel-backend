<?php

namespace App\Filament\Resources\VoucherBatches\Pages;

use App\Filament\Concerns\CoNutQuayLaiDanhSach;
use App\Filament\Resources\VoucherBatches\VoucherBatchResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVoucherBatch extends CreateRecord
{
    use CoNutQuayLaiDanhSach;

    protected static string $resource = VoucherBatchResource::class;

    // Lưu xong ở lại trang sửa: xem thử vị trí ô mã trên ảnh thật, rồi tạo mã
    protected function getRedirectUrl(): string
    {
        return VoucherBatchResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
