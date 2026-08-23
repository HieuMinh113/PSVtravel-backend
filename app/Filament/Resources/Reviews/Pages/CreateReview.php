<?php

namespace App\Filament\Resources\Reviews\Pages;

use App\Filament\Resources\Reviews\ReviewResource;
use App\Filament\Concerns\CoNutQuayLaiDanhSach;
use Filament\Resources\Pages\CreateRecord;

class CreateReview extends CreateRecord
{
    use CoNutQuayLaiDanhSach;

    protected static string $resource = ReviewResource::class;
}
