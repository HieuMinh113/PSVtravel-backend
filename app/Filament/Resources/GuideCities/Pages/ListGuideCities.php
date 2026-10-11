<?php

namespace App\Filament\Resources\GuideCities\Pages;

use App\Filament\Resources\GuideCities\GuideCityResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGuideCities extends ListRecords
{
    protected static string $resource = GuideCityResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Thêm thành phố')];
    }
}
