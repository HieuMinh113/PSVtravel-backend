<?php

namespace App\Filament\Resources\VisaCases\Pages;

use App\Filament\Resources\VisaCases\VisaCaseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVisaCase extends CreateRecord
{
    protected static string $resource = VisaCaseResource::class;

    /** Tạo xong ở lại trang sửa để đánh dấu giấy tờ / tải file tiếp. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
