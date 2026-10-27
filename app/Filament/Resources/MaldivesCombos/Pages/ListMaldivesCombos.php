<?php

namespace App\Filament\Resources\MaldivesCombos\Pages;

use App\Filament\Resources\MaldivesCombos\Actions\NhapComboTuFileAction;
use App\Filament\Resources\MaldivesCombos\MaldivesComboResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMaldivesCombos extends ListRecords
{
    protected static string $resource = MaldivesComboResource::class;

    protected function getHeaderActions(): array
    {
        return [NhapComboTuFileAction::make(), CreateAction::make()];
    }
}
