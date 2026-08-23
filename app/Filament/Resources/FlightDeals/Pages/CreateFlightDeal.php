<?php

namespace App\Filament\Resources\FlightDeals\Pages;

use App\Filament\Resources\FlightDeals\FlightDealResource;
use App\Filament\Concerns\CoNutQuayLaiDanhSach;
use Filament\Resources\Pages\CreateRecord;

class CreateFlightDeal extends CreateRecord
{
    use CoNutQuayLaiDanhSach;

    protected static string $resource = FlightDealResource::class;
}
