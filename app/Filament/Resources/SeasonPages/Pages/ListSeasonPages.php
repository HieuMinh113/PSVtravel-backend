<?php

namespace App\Filament\Resources\SeasonPages\Pages;

use App\Filament\Resources\SeasonPages\SeasonPageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSeasonPages extends ListRecords
{
    protected static string $resource = SeasonPageResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
