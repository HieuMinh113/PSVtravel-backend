<?php

namespace App\Filament\Resources\Faqs\Pages;

use App\Filament\Resources\Faqs\FaqResource;
use App\Filament\Concerns\CoNutQuayLaiDanhSach;
use Filament\Resources\Pages\CreateRecord;

class CreateFaq extends CreateRecord
{
    use CoNutQuayLaiDanhSach;

    protected static string $resource = FaqResource::class;
}
