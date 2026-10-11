<?php

namespace App\Filament\Resources\GuideCountries\Pages;

use App\Filament\Resources\GuideCountries\GuideCountryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGuideCountry extends EditRecord
{
    protected static string $resource = GuideCountryResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
