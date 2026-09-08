<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'summary' => $this->summary,
            'audience' => array_values($this->audience ?? []),
            'cover_image' => $this->anh($this->cover_image),
            'group_size' => $this->group_size,
            'duration' => $this->duration,
            'location' => $this->location,
            'price_note' => $this->price_note,
            'rating' => $this->rating ? (float) $this->rating : null,
            'review_count' => (int) $this->review_count,
            'is_featured' => (bool) $this->is_featured,
        ];
    }

    private function anh(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return str_starts_with($path, 'http') ? $path : asset('storage/'.$path);
    }
}
