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
            // Tách lúc trả về: tour nhập từ trước có cả đoạn văn dồn vào MỘT
            // mục kèm dấu ➢, để nguyên thì ngoài web thành một dòng dài chạy
            // tràn khỏi khung.
            'included' => \Modules\Tour\Models\Tour::tachTungMuc($this->included ?? []),
            'excluded' => \Modules\Tour\Models\Tour::tachTungMuc($this->excluded ?? []),
            'cancellation_policy' => $this->cancellation_policy,

            // Khối "Những thông tin cần lưu ý" ở cuối trang tour.
            // Mục để trống nội dung thì không gửi ra — khách bấm vào một dòng
            // rỗng sẽ tưởng trang bị lỗi.
            'notes' => collect($this->notes ?? [])
                ->filter(fn ($muc) => filled($muc['title'] ?? null) && filled($muc['content'] ?? null))
                ->map(fn ($muc) => [
                    'title' => $muc['title'],
                    'content' => $muc['content'],
                ])
                ->values(),
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
                // Ảnh của riêng ngày này, đúng thứ tự nhân viên đã sắp trong admin
                'images' => collect($it->images ?? [])
                    ->map(fn ($path) => str_starts_with($path, 'http') ? $path : asset('storage/'.$path))
                    ->values(),
            ]),

            // Chỉ đợt còn hiệu lực, còn chỗ
            'departures' => $this->departures
                ->filter(fn ($d) => $d->status === 'open' && ! $d->start_date->lt(now()->startOfDay()))
                ->values()
                ->map(fn ($d) => [
                    'id' => $d->id,
                    'start_date' => $d->start_date->format('Y-m-d'),
                    'start_date_display' => $d->start_date->format('d/m/Y'),
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
                    'is_verified' => (bool) $r->user_id,
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