<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Concerns\CoNutQuayLaiDanhSach;
use Filament\Resources\Pages\CreateRecord;

class CreateBooking extends CreateRecord
{
    use CoNutQuayLaiDanhSach;

    protected static string $resource = BookingResource::class;

    /** Đơn mới chưa có khoản thu nào → luôn "Chưa trả"; tự tính lại khi thêm khoản thu. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['payment_status'] = 'unpaid';

        return $data;
    }
}
