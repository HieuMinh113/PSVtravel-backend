<?php

namespace Modules\Review\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $cover = $this->tour?->cover_image;

        return [
            'name' => $this->customer_name,
            'rating' => $this->rating,
            'content' => $this->content,
            'tour_name' => $this->tour?->name,
            'tour_image' => $cover
                ? (str_starts_with($cover, 'http') ? $cover : asset('storage/'.$cover))
                : null,
        ];
    }
}