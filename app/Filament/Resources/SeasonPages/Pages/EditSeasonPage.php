<?php

namespace App\Filament\Resources\SeasonPages\Pages;

use App\Filament\Resources\SeasonPages\SeasonPageResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditSeasonPage extends EditRecord
{
    protected static string $resource = SeasonPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
