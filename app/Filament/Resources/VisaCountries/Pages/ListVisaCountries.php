<?php

namespace App\Filament\Resources\VisaCountries\Pages;

use App\Filament\Resources\VisaCountries\VisaCountryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVisaCountries extends ListRecords
{
    protected static string $resource = VisaCountryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
