<?php

namespace Modules\Tour\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TourListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'type' => $this->type,
            'region' => $this->region,
            'country' => $this->country,
            'duration_days' => $this->duration_days,
            'duration_nights' => $this->duration_nights,
            'departure_from' => $this->departure_from,
            'adult_price' => $this->adult_price,
            'old_price' => $this->old_price,
            'tag' => $this->tag,
            'cover_image' => $this->cover_image
                ? (str_starts_with($this->cover_image, 'http') ? $this->cover_image : asset('storage/'.$this->cover_image))
                : null,
            'rating' => $this->rating,
            'review_count' => $this->review_count,
            'is_featured' => $this->is_featured,
            'updated_at' => $this->updated_at,
        ];
    }
}