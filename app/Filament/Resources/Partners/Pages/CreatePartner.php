<?php

namespace App\Filament\Resources\Partners\Pages;

use App\Filament\Resources\Partners\PartnerResource;
use App\Filament\Concerns\CoNutQuayLaiDanhSach;
use Filament\Resources\Pages\CreateRecord;

class CreatePartner extends CreateRecord
{
    use CoNutQuayLaiDanhSach;

    protected static string $resource = PartnerResource::class;
}
