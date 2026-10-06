<?php

namespace App\Filament\Resources\AllianceSources\Pages;

use App\Filament\Resources\AllianceSources\Actions\DocNgayAction;
use App\Filament\Resources\AllianceSources\AllianceSourceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAllianceSource extends EditRecord
{
    protected static string $resource = AllianceSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DocNgayAction::make(),
            DeleteAction::make(),
        ];
    }

    /** Đổi link thì đọc lại ngay theo link mới. */
    protected function afterSave(): void
    {
        if ($this->record->wasChanged('sheet_url') && $this->record->is_active) {
            DocNgayAction::chay($this->record);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
