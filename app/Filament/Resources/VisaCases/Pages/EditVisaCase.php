<?php

namespace App\Filament\Resources\VisaCases\Pages;

use App\Filament\Resources\VisaCases\Actions\TinNhanGiayThieuAction;
use App\Filament\Resources\VisaCases\VisaCaseResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVisaCase extends EditRecord
{
    protected static string $resource = VisaCaseResource::class;

    public function getTitle(): string
    {
        return $this->record->code.' — '.$this->record->full_name;
    }

    protected function getHeaderActions(): array
    {
        return [
            TinNhanGiayThieuAction::make(),
            DeleteAction::make(),
        ];
    }
}
