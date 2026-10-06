<?php

namespace App\Filament\Resources\Destinations\Pages;

use App\Filament\Resources\Destinations\DestinationResource;
use App\Filament\Concerns\CoNutQuayLaiDanhSach;
use Filament\Resources\Pages\CreateRecord;

class CreateDestination extends CreateRecord
{
    use CoNutQuayLaiDanhSach;

    protected static string $resource = DestinationResource::class;
}
