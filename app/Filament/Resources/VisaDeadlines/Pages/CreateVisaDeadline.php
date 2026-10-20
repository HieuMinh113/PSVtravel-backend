<?php

namespace App\Filament\Resources\VisaDeadlines\Pages;

use App\Filament\Resources\VisaDeadlines\VisaDeadlineResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVisaDeadline extends CreateRecord
{
    protected static string $resource = VisaDeadlineResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
