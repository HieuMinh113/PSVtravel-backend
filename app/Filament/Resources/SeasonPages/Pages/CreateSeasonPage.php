<?php

namespace App\Filament\Resources\SeasonPages\Pages;

use App\Filament\Concerns\CoNutQuayLaiDanhSach;
use App\Filament\Resources\SeasonPages\SeasonPageResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSeasonPage extends CreateRecord
{
    use CoNutQuayLaiDanhSach;

    protected static string $resource = SeasonPageResource::class;
}
