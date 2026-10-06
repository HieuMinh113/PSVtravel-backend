<?php

namespace App\Filament\Resources\VisaChecklists\Pages;

use App\Filament\Resources\VisaChecklists\VisaChecklistResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVisaChecklist extends CreateRecord
{
    protected static string $resource = VisaChecklistResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
