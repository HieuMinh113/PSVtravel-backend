<?php

namespace App\Filament\Resources\MaldivesResorts\Pages;

use App\Filament\Resources\MaldivesResorts\MaldivesResortResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditMaldivesResort extends EditRecord
{
    protected static string $resource = MaldivesResortResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make(), ForceDeleteAction::make(), RestoreAction::make()];
    }
}
