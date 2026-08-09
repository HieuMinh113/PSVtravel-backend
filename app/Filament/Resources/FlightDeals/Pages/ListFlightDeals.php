<?php

namespace App\Filament\Resources\FlightDeals\Pages;

use App\Filament\Resources\FlightDeals\FlightDealResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFlightDeals extends ListRecords
{
    protected static string $resource = FlightDealResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
