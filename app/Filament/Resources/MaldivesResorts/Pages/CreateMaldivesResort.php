<?php

namespace App\Filament\Resources\MaldivesResorts\Pages;

use App\Filament\Concerns\CoNutQuayLaiDanhSach;
use App\Filament\Resources\MaldivesResorts\MaldivesResortResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMaldivesResort extends CreateRecord
{
    use CoNutQuayLaiDanhSach;

    protected static string $resource = MaldivesResortResource::class;
}
