<?php

namespace Modules\Guide\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuideDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'cover_image' => $this->anh($this->cover_image),
            'category' => $this->category,
            'author_name' => $this->whenLoaded('author', fn () => $this->author?->name),
            'view_count' => $this->view_count,
            'published_at' => $this->published_at?->format('Y-m-d'),
            'meta_title' => $this->title,
            'meta_description' => $this->excerpt,
            // Tour gắn kèm để hiện ô đặt tour bên bài viết (null nếu admin không gắn).
            'tour' => $this->whenLoaded('tour', fn () => $this->tour ? $this->duLieuTour($this->tour) : null),
        ];
    }

    // Rút gọn tour về đúng những trường ô đặt tour cần (giá, đợt khởi hành, số chỗ).
    private function duLieuTour($tour): array
    {
        $departures = $tour->departures
            ->filter(fn ($d) => $d->status === 'open' && ! $d->start_date->lt(now()->startOfDay()))
            ->values();
        $dauTien = $departures->first();

        return [
            'id' => $tour->id,
            'slug' => $tour->slug,
            'name' => $tour->name,
            'type' => $tour->type,
            'region' => $tour->region,
            'country' => $tour->country,
            'duration_days' => $tour->duration_days,
            'duration_nights' => $tour->duration_nights,
            'departure_from' => $tour->departure_from,
            'adult_price' => $tour->adult_price,
            'child_price' => $tour->child_price,
            'old_price' => $tour->old_price,
            'cover_image' => $this->anh($tour->cover_image),
            'next_start_date' => $dauTien?->start_date?->format('d/m/Y'),
            'next_seats_left' => $dauTien?->seats_left,
            'departures' => $departures->map(fn ($d) => [
                'id' => $d->id,
                'start_date' => $d->start_date->format('Y-m-d'),
                'start_date_display' => $d->start_date->format('d/m/Y'),
                'price' => $d->price_override ?? $tour->adult_price,
                'seats_left' => $d->seats_left,
            ])->all(),
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