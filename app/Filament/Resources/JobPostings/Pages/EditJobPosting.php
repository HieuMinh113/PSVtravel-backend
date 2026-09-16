<?php

namespace App\Filament\Resources\JobPostings\Pages;

use App\Filament\Resources\JobPostings\JobPostingResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditJobPosting extends EditRecord
{
    protected static string $resource = JobPostingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
