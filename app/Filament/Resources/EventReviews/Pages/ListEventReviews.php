<?php

namespace App\Filament\Resources\EventReviews\Pages;

use App\Filament\Resources\EventReviews\EventReviewResource;
use Filament\Resources\Pages\ListRecords;

class ListEventReviews extends ListRecords
{
    protected static string $resource = EventReviewResource::class;

    // Không có nút "Tạo mới": đánh giá đến từ form ngoài website
    protected function getHeaderActions(): array
    {
        return [];
    }
}
