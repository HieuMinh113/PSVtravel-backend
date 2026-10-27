<?php

namespace App\Filament\Resources\MaldivesResorts\Pages;

use App\Filament\Resources\MaldivesResorts\MaldivesResortResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMaldivesResorts extends ListRecords
{
    protected static string $resource = MaldivesResortResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
