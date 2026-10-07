<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Resources\Bookings\Actions\NhacDongTienAction;
use App\Filament\Resources\Bookings\Actions\PhieuXacNhanAction;
use App\Filament\Resources\Bookings\BookingResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewBooking extends ViewRecord
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            NhacDongTienAction::make(),
            PhieuXacNhanAction::make(),
            EditAction::make(),
        ];
    }
}
