<?php

namespace App\Filament\Resources\MaldivesBookings\Pages;

use App\Filament\Concerns\CoNutQuayLaiDanhSach;
use App\Filament\Resources\MaldivesBookings\MaldivesBookingResource;
use App\Filament\Resources\MaldivesBookings\Schemas\MaldivesBookingForm;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Maldives\Models\MaldivesBooking;

class CreateMaldivesBooking extends CreateRecord
{
    use CoNutQuayLaiDanhSach;

    protected static string $resource = MaldivesBookingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return MaldivesBookingForm::chuanBi($data);
    }

    protected function handleRecordCreation(array $data): Model
    {
        // Nhân viên tạo đơn (khách gọi điện / đến văn phòng) — người tạo phụ trách
        $don = (new MaldivesBooking)->forceFill(['source' => 'quan_tri'])->fill($data);
        $don->save();

        return $don;
    }
}
