<?php

namespace App\Filament\Resources\Tours\Pages;

use App\Filament\Resources\Tours\Actions\NhapTuFileAction;
use App\Filament\Resources\Tours\TourResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTours extends ListRecords
{
    protected static string $resource = TourResource::class;

    protected function getHeaderActions(): array
    {
        return [
            NhapTuFileAction::make(),
            CreateAction::make(),
        ];
    }
}
