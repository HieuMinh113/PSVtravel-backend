<?php

namespace App\Filament\Resources\Moments\Pages;

use App\Filament\Resources\Moments\MomentResource;
use App\Filament\Concerns\CoNutQuayLaiDanhSach;
use Filament\Resources\Pages\CreateRecord;

class CreateMoment extends CreateRecord
{
    use CoNutQuayLaiDanhSach;

    protected static string $resource = MomentResource::class;
}
