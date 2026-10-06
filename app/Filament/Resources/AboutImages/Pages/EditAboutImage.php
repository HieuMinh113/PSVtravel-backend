<?php

namespace App\Filament\Resources\AboutImages\Pages;

use App\Filament\Resources\AboutImages\AboutImageResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditAboutImage extends EditRecord
{
    protected static string $resource = AboutImageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
