<?php

namespace App\Filament\Resources\VisaCountries\Pages;

use App\Filament\Resources\VisaCountries\VisaCountryResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditVisaCountry extends EditRecord
{
    protected static string $resource = VisaCountryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
