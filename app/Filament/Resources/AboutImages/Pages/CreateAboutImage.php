<?php

namespace App\Filament\Resources\AboutImages\Pages;

use App\Filament\Resources\AboutImages\AboutImageResource;
use App\Filament\Concerns\CoNutQuayLaiDanhSach;
use Filament\Resources\Pages\CreateRecord;

class CreateAboutImage extends CreateRecord
{
    use CoNutQuayLaiDanhSach;

    protected static string $resource = AboutImageResource::class;
}
