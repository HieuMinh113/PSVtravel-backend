<?php

namespace App\Filament\Resources\GuideCities\Pages;

use App\Filament\Resources\GuideCities\GuideCityResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGuideCity extends CreateRecord
{
    protected static string $resource = GuideCityResource::class;

    // Tạo xong mở ngay trang sửa để thêm điểm
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
