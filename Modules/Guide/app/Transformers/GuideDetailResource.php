<?php

namespace Modules\Guide\Transformers;

use App\Services\YouTube;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Tour\Services\HanNopVisa;
use Modules\Tour\Transformers\TourDetailResource;

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
            // Chỉ trả MÃ video — website tự dựng link nhúng youtube-nocookie
            'video_id' => YouTube::id($this->video_url),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'category' => $this->category,
            'author_name' => $this->whenLoaded('author', fn () => $this->author?->name),
            'view_count' => $this->view_count,
            'published_at' => $this->published_at?->format('Y-m-d'),
            // Ưu tiên "Tiêu đề SEO" nhân viên tự viết; trống thì dùng tiêu đề bài
            'meta_title' => $this->seo_title ?: $this->title,
            'meta_description' => $this->excerpt,
            // Tour gắn kèm để hiện ô đặt tour bên bài viết (null nếu admin không gắn).
            'tour' => $this->whenLoaded('tour', fn () => $this->tour ? $this->duLieuTour($this->tour) : null),
            // Bài nằm ở đâu trên bản đồ cẩm nang — trang bài mở bản đồ đúng thành phố,
            // đánh dấu điểm "đang đọc". Chỉ tính điểm / thành phố / nước đang hiện.
            'map_spots' => $this->whenLoaded('spots', fn () => $this->spots
                ->filter(fn ($d) => $d->status === 'published' && $d->city?->status === 'published' && $d->city->country?->status === 'published')
                ->sortBy('sort_order')
                ->map(fn ($d) => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'lat' => $d->lat,
                    'lng' => $d->lng,
                    'tp' => $d->city->slug,
                    'ten_tp' => $d->city->name,
                    'nuoc' => $d->city->country->slug,
                ])->values()),
            'map_cities' => $this->whenLoaded('mapCities', fn () => $this->mapCities
                ->filter(fn ($t) => $t->status === 'published' && $t->country?->status === 'published')
                ->map(fn ($t) => ['tp' => $t->slug, 'ten_tp' => $t->name, 'nuoc' => $t->country->slug])
                ->values()),
        ];
    }

    // Rút gọn tour về đúng những trường ô đặt tour cần (giá, đợt khởi hành, số chỗ).
    private function duLieuTour($tour): array
    {
        // (bỏ cả đợt đã quá hạn nộp visa — khách không đặt được nữa)
        $departures = $tour->departures
            ->filter(fn ($d) => $d->status === 'open' && ! $d->start_date->lt(now()->startOfDay()) && ! $d->quaHanVisa($tour))
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
            'infant_price' => $tour->infant_price,
            'old_price' => $tour->old_price,
            'cover_image' => $this->anh($tour->cover_image),
            'het_han_visa' => $tour->hetHanVisa(),
            'visa_deadline_days' => HanNopVisa::soNgayNop($tour),
            ...TourDetailResource::dangVisa($tour),
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
