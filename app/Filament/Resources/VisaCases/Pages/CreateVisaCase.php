<?php

namespace App\Filament\Resources\VisaCases\Pages;

use App\Filament\Resources\VisaCases\VisaCaseResource;
use Filament\Resources\Pages\CreateRecord;
use Modules\Visa\Models\VisaCase;

class CreateVisaCase extends CreateRecord
{
    protected static string $resource = VisaCaseResource::class;

    /** Nhân viên tạo hồ sơ thì hồ sơ là của chính họ — không tự giao cho người khác. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! auth()->user()?->can('giao', VisaCase::class)) {
            $data['assigned_to'] = auth()->id();
        }

        return $data;
    }

    /** Tạo xong ở lại trang sửa để đánh dấu giấy tờ / tải file tiếp. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
