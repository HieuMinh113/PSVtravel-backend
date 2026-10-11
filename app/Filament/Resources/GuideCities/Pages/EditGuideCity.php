<?php

namespace App\Filament\Resources\GuideCities\Pages;

use App\Filament\Resources\GuideCities\GuideCityResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditGuideCity extends EditRecord
{
    protected static string $resource = GuideCityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('xemWeb')
                ->label('Xem trên website')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn () => rtrim((string) config('app.frontend_url', ''), '/').'/cam-nang?nuoc='
                    .$this->getRecord()->country?->slug.'&tp='.$this->getRecord()->slug, shouldOpenInNewTab: true)
                ->visible(fn () => filled(config('app.frontend_url'))),
            DeleteAction::make(),
        ];
    }
}
