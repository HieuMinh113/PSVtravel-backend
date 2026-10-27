<?php

namespace App\Filament\Resources\MaldivesCombos\Pages;

use App\Filament\Resources\MaldivesCombos\MaldivesComboResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditMaldivesCombo extends EditRecord
{
    protected static string $resource = MaldivesComboResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('xemWeb')->label('Xem trên web')->icon('heroicon-o-eye')->color('gray')
                ->url(fn () => rtrim((string) config('app.frontend_url', 'https://psvtravel.com'), '/').'/maldives/'.$this->record->slug)
                ->openUrlInNewTab()
                ->visible(fn () => $this->record->status === 'published'),
            DeleteAction::make(), ForceDeleteAction::make(), RestoreAction::make(),
        ];
    }
}
