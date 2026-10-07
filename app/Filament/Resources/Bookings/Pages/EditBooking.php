<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Resources\Bookings\Actions\NhacDongTienAction;
use App\Filament\Resources\Bookings\Actions\PhieuXacNhanAction;
use App\Filament\Resources\Bookings\BookingResource;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditBooking extends EditRecord
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            NhacDongTienAction::make(),
            PhieuXacNhanAction::make(),
            ViewAction::make(),
            RestoreAction::make(),
        ];
    }
}
