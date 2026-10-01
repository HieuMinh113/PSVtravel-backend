<?php

namespace App\Filament\Resources\Tours\Pages;

use App\Filament\Resources\Tours\Actions\MaQrAction;
use App\Filament\Resources\Tours\TourResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTour extends ViewRecord
{
    protected static string $resource = TourResource::class;

    protected function getHeaderActions(): array
    {
        return [
            MaQrAction::make(),
            EditAction::make(),
        ];
    }
    public function getTitle(): string
    {
        return 'Xem tour: '.$this->getRecord()->name;
    }
    public function getBreadcrumb(): string
    {
        return 'Xem chi tiết';
    }
}
