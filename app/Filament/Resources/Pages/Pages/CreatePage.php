<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use App\Filament\Concerns\CoNutQuayLaiDanhSach;
use Filament\Resources\Pages\CreateRecord;

class CreatePage extends CreateRecord
{
    use CoNutQuayLaiDanhSach;

    protected static string $resource = PageResource::class;
}
