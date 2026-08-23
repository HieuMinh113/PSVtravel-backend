<?php

namespace App\Filament\Resources\Airlines\Pages;

use App\Filament\Resources\Airlines\AirlineResource;
use App\Filament\Concerns\CoNutQuayLaiDanhSach;
use Filament\Resources\Pages\CreateRecord;

class CreateAirline extends CreateRecord
{
    use CoNutQuayLaiDanhSach;

    protected static string $resource = AirlineResource::class;
}
