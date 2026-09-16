<?php

namespace App\Filament\Resources\AboutImages\Pages;

use App\Filament\Resources\AboutImages\AboutImageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAboutImages extends ListRecords
{
    protected static string $resource = AboutImageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
