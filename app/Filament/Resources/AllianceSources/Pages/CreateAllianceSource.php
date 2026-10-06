<?php

namespace App\Filament\Resources\AllianceSources\Pages;

use App\Filament\Resources\AllianceSources\Actions\DocNgayAction;
use App\Filament\Resources\AllianceSources\AllianceSourceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAllianceSource extends CreateRecord
{
    protected static string $resource = AllianceSourceResource::class;

    /** Đọc thử ngay sau khi lưu để điều hành biết link dùng được hay không. */
    protected function afterCreate(): void
    {
        if ($this->record->is_active) {
            DocNgayAction::chay($this->record);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
