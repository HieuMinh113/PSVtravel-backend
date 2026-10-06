<?php

namespace App\Filament\Resources\EventReviews\Pages;

use App\Filament\Resources\EventReviews\EventReviewResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEventReview extends ViewRecord
{
    protected static string $resource = EventReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
