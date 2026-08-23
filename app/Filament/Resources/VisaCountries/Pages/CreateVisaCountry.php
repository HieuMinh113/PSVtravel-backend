<?php

namespace App\Filament\Resources\VisaCountries\Pages;

use App\Filament\Resources\VisaCountries\VisaCountryResource;
use App\Filament\Concerns\CoNutQuayLaiDanhSach;
use Filament\Resources\Pages\CreateRecord;

class CreateVisaCountry extends CreateRecord
{
    use CoNutQuayLaiDanhSach;

    protected static string $resource = VisaCountryResource::class;
}
