<?php

namespace App\Filament\Resources\FlightDeals\Pages;

use App\Filament\Resources\FlightDeals\FlightDealResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewFlightDeal extends ViewRecord
{
    protected static string $resource = FlightDealResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
