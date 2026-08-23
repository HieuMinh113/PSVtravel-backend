<?php

namespace App\Filament\Resources\Banners\Pages;

use App\Filament\Resources\Banners\BannerResource;
use App\Filament\Concerns\CoNutQuayLaiDanhSach;
use Filament\Resources\Pages\CreateRecord;

class CreateBanner extends CreateRecord
{
    use CoNutQuayLaiDanhSach;

    protected static string $resource = BannerResource::class;
}
