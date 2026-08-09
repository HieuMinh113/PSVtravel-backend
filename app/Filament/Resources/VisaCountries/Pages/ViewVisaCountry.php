<?php

namespace App\Filament\Resources\VisaCountries\Pages;

use App\Filament\Resources\VisaCountries\VisaCountryResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVisaCountry extends ViewRecord
{
    protected static string $resource = VisaCountryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
