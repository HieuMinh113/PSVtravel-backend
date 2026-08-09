<?php

namespace App\Filament\Resources\Moments\Pages;

use App\Filament\Resources\Moments\MomentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMoment extends ViewRecord
{
    protected static string $resource = MomentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
