<?php

namespace App\Filament\Resources\MaldivesBookings\Pages;

use App\Filament\Resources\MaldivesBookings\MaldivesBookingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMaldivesBookings extends ListRecords
{
    protected static string $resource = MaldivesBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tạo đơn cho khách gọi điện')];
    }
}
