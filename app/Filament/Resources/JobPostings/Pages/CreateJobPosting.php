<?php

namespace App\Filament\Resources\JobPostings\Pages;

use App\Filament\Resources\JobPostings\JobPostingResource;
use App\Filament\Concerns\CoNutQuayLaiDanhSach;
use Filament\Resources\Pages\CreateRecord;

class CreateJobPosting extends CreateRecord
{
    use CoNutQuayLaiDanhSach;

    protected static string $resource = JobPostingResource::class;
}
