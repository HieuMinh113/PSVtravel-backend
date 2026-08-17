<?php

namespace Modules\Tour\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TourDetailResource extends JsonResource
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
            'child_price' => $this->child_price,
            'old_price' => $this->old_price,
            'tag' => $this->tag,
            'cover_image' => $this->cover_image
                ? (str_starts_with($this->cover_image, 'http') ? $this->cover_image : asset('storage/'.$this->cover_image))
                : null,
            'highlights' => $this->highlights ?? [],
            'included' => $this->included ?? [],
            'excluded' => $this->excluded ?? [],
            'cancellation_policy' => $this->cancellation_policy,
            'description' => $this->description,
            'rating' => $this->rating,
            'review_count' => $this->review_count,

            'images' => $this->images->map(fn ($img) => [
                'url' => str_starts_with($img->path, 'http') ? $img->path : asset('storage/'.$img->path),
                'alt' => $img->alt,
            ]),

            'itineraries' => $this->itineraries->map(fn ($it) => [
                'day_number' => $it->day_number,
                'title' => $it->title,
                'description' => $it->description,
            ]),

            // Chỉ đợt còn hiệu lực, còn chỗ
            'departures' => $this->departures
                ->filter(fn ($d) => $d->status === 'open' && ! $d->start_date->lt(now()->startOfDay()))
                ->values()
                ->map(fn ($d) => [
                    'id' => $d->id,
                    'start_date' => $d->start_date->format('Y-m-d'),
                    'price' => $d->price_override ?? $this->adult_price,
                    'seats_left' => $d->seats_left,
                ]),

            'categories' => $this->categories->map(fn ($c) => [
                'slug' => $c->slug,
                'name' => $c->name,
            ]),

            'reviews' => $this->reviews
                ->where('status', 'approved')
                ->take(20)
                ->map(fn ($r) => [
                    'customer_name' => $r->customer_name,
                    'rating' => $r->rating,
                    'content' => $r->content,
                    'admin_reply' => $r->admin_reply,
                    'created_at' => $r->created_at->format('Y-m-d'),
                ])
                ->values(),

            'updated_at' => $this->updated_at,
        ];
    }
}