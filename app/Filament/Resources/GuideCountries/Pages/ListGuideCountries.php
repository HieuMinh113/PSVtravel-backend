<?php

namespace App\Filament\Resources\GuideCountries\Pages;

use App\Filament\Resources\GuideCountries\GuideCountryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGuideCountries extends ListRecords
{
    protected static string $resource = GuideCountryResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Thêm nước')];
    }
}
