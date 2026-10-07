<?php

namespace App\Filament\Resources\VisaChecklists\Pages;

use App\Filament\Resources\VisaChecklists\Actions\XuatMauAction;
use App\Filament\Resources\VisaChecklists\VisaChecklistResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVisaChecklist extends EditRecord
{
    protected static string $resource = VisaChecklistResource::class;

    protected function getHeaderActions(): array
    {
        return [XuatMauAction::make(), DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
