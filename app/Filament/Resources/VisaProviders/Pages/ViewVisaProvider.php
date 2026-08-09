<?php

namespace App\Filament\Resources\VisaProviders\Pages;

use App\Filament\Resources\VisaProviders\VisaProviderResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVisaProvider extends ViewRecord
{
    protected static string $resource = VisaProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
