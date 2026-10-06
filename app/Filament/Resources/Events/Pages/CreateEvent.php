<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Concerns\CoNutQuayLaiDanhSach;
use App\Filament\Resources\Events\EventResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEvent extends CreateRecord
{
    use CoNutQuayLaiDanhSach;

    protected static string $resource = EventResource::class;
}
