<?php

namespace App\Filament\Resources\Redirects\Pages;

use App\Filament\Concerns\CoNutQuayLaiDanhSach;
use App\Filament\Resources\Redirects\RedirectResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRedirect extends CreateRecord
{
    use CoNutQuayLaiDanhSach;

    protected static string $resource = RedirectResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'source' => 'tay'];
    }
}
