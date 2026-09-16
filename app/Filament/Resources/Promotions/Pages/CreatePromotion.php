<?php

namespace App\Filament\Resources\Promotions\Pages;

use App\Filament\Resources\Promotions\PromotionResource;
use App\Filament\Concerns\CoNutQuayLaiDanhSach;
use Filament\Resources\Pages\CreateRecord;

class CreatePromotion extends CreateRecord
{
    use CoNutQuayLaiDanhSach;

    protected static string $resource = PromotionResource::class;
}
