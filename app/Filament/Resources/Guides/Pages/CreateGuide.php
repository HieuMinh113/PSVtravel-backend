<?php

namespace App\Filament\Resources\Guides\Pages;

use App\Filament\Resources\Guides\GuideResource;
use App\Filament\Concerns\CoNutQuayLaiDanhSach;
use Filament\Resources\Pages\CreateRecord;

class CreateGuide extends CreateRecord
{
    use CoNutQuayLaiDanhSach;

    protected static string $resource = GuideResource::class;
}
