<?php

namespace App\Filament\Resources\VisaProviders\Pages;

use App\Filament\Resources\VisaProviders\VisaProviderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVisaProviders extends ListRecords
{
    protected static string $resource = VisaProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
