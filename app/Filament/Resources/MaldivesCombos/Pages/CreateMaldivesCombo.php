<?php

namespace App\Filament\Resources\MaldivesCombos\Pages;

use App\Filament\Concerns\CoNutQuayLaiDanhSach;
use App\Filament\Resources\MaldivesCombos\MaldivesComboResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMaldivesCombo extends CreateRecord
{
    use CoNutQuayLaiDanhSach;

    protected static string $resource = MaldivesComboResource::class;
}
