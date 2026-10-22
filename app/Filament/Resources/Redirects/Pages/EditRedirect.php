<?php

namespace App\Filament\Resources\Redirects\Pages;

use App\Filament\Resources\Redirects\RedirectResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRedirect extends EditRecord
{
    protected static string $resource = RedirectResource::class;

    // Nhân viên đã sửa thì "Tự khớp lại" không ghi đè nữa
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return [...$data, 'source' => 'tay'];
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
