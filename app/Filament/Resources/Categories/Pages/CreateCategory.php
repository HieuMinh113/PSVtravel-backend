<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Concerns\CoNutQuayLaiDanhSach;
use Filament\Resources\Pages\CreateRecord;

class CreateCategory extends CreateRecord
{
    use CoNutQuayLaiDanhSach;

    protected static string $resource = CategoryResource::class;
}
