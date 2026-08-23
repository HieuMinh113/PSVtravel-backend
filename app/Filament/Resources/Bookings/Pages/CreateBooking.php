<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Concerns\CoNutQuayLaiDanhSach;
use Filament\Resources\Pages\CreateRecord;

class CreateBooking extends CreateRecord
{
    use CoNutQuayLaiDanhSach;

    protected static string $resource = BookingResource::class;
}
