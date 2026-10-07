<?php

namespace App\Filament\Resources\VisaCases\Pages;

use App\Filament\Resources\VisaCases\Actions\TinNhanGiayThieuAction;
use App\Filament\Resources\VisaCases\VisaCaseResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Visa\Models\VisaCase;

class EditVisaCase extends EditRecord
{
    protected static string $resource = VisaCaseResource::class;

    public function getTitle(): string
    {
        return $this->record->code.' — '.$this->record->full_name;
    }

    /** Không có quyền giao thì không đổi được người phụ trách, dù gửi gì lên. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! auth()->user()?->can('giao', VisaCase::class)) {
            unset($data['assigned_to']);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            TinNhanGiayThieuAction::make(),
            DeleteAction::make(),
        ];
    }
}
