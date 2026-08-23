<?php

namespace App\Filament\Resources\VisaProviders\Pages;

use App\Filament\Resources\VisaProviders\VisaProviderResource;
use App\Filament\Concerns\CoNutQuayLaiDanhSach;
use Filament\Resources\Pages\CreateRecord;

class CreateVisaProvider extends CreateRecord
{
    use CoNutQuayLaiDanhSach;

    protected static string $resource = VisaProviderResource::class;
}
