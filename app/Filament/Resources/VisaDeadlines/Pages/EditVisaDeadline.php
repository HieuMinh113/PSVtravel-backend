<?php

namespace App\Filament\Resources\VisaDeadlines\Pages;

use App\Filament\Resources\VisaDeadlines\VisaDeadlineResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVisaDeadline extends EditRecord
{
    protected static string $resource = VisaDeadlineResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
