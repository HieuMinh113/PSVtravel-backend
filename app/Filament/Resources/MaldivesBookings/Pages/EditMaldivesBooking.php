<?php

namespace App\Filament\Resources\MaldivesBookings\Pages;

use App\Filament\Resources\MaldivesBookings\MaldivesBookingResource;
use App\Filament\Resources\MaldivesBookings\Schemas\MaldivesBookingForm;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditMaldivesBooking extends EditRecord
{
    protected static string $resource = MaldivesBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return MaldivesBookingForm::chuanBi($data);
    }
}
