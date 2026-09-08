<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'summary' => $this->summary,
            'audience' => array_values($this->audience ?? []),
            'cover_image' => $this->anh($this->cover_image),
            'gallery' => collect($this->gallery ?? [])
                ->map(fn ($p) => $this->anh($p))
                ->filter()
                ->values(),
            'description' => $this->description,
            // "Sẽ có gì" — bỏ dòng trống để giao diện không hiện gạch đầu dòng rỗng
            'includes' => collect($this->includes ?? [])
                ->map(fn ($x) => is_string($x) ? trim($x) : $x)
                ->filter()
                ->values(),
            'itinerary' => collect($this->itinerary ?? [])
                ->map(fn ($ngay) => [
                    'title' => $ngay['title'] ?? '',
                    'description' => $ngay['description'] ?? '',
                    'images' => collect($ngay['images'] ?? [])
                        ->map(fn ($p) => $this->anh($p))
                        ->filter()
                        ->values(),
                ])
                ->filter(fn ($ngay) => ($ngay['title'] ?? '') !== '' || ($ngay['description'] ?? '') !== '' || count($ngay['images']) > 0)
                ->values(),
            'rating' => $this->rating ? (float) $this->rating : null,
            'review_count' => (int) $this->review_count,
            'group_size' => $this->group_size,
            'duration' => $this->duration,
            'location' => $this->location,
            'price_note' => $this->price_note,
            'meta_title' => $this->title,
            'meta_description' => $this->summary,
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
