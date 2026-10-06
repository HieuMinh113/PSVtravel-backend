<?php

namespace Modules\Tour\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TourListResource extends JsonResource
{
    /** Số ngày khởi hành hiện trên thẻ tour; còn nữa thì gộp thành nhãn "+n". */
    private const SO_NGAY_HIEN = 5;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'type' => $this->type,
            'region' => $this->region,
            'country' => $this->country,
            // Danh sách slug danh mục để trang danh sách lọc được khi khách bấm
            // vào một điểm đến ở trang chủ. Chỉ trả khi đã nạp sẵn quan hệ,
            // tránh N+1 khi liệt kê nhiều tour.
            'category_slugs' => $this->whenLoaded('categories', fn () => $this->categories->pluck('slug')),
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
            'next_start_date' => optional($this->departures->first())?->start_date?->format('d/m/Y'),
            'next_seats_left' => optional($this->departures->first())?->seats_left,
            // Dãy ngày khởi hành sắp tới cho thẻ tour ngoài trang danh sách:
            // nhìn một dãy ngày là biết tour chạy đều, dễ chọn hơn hẳn so với
            // một ngày lẻ. Quan hệ departures đã được lọc còn mở, còn hạn và
            // sắp theo ngày ngay ở controller.
            //
            // Trả về dạng Y-m-d chứ không phải d/m: giao diện còn phải SO SÁNH
            // với ngày khách chọn ở ô tìm kiếm, mà d/m thì không có năm nên
            // không so được. Việc hiển thị d/m để bên giao diện lo.
            'departure_dates' => $this->departures->take(self::SO_NGAY_HIEN)
                ->map(fn ($d) => $d->start_date->format('Y-m-d'))
                ->values(),
            'departure_count' => $this->departures->count(),
            // Đợt XA NHẤT còn mở. Khách tìm "khởi hành từ ngày X" thì tour hợp
            // lệ khi còn ít nhất một đợt từ ngày X trở đi — tức là đợt xa nhất
            // phải >= X. Chỉ cần một mốc này, khỏi gửi cả trăm ngày về máy khách.
            'last_departure_date' => optional($this->departures->last())?->start_date?->format('Y-m-d'),
            // Số chỗ trống nhiều nhất trong các đợt còn mở, để lọc theo số khách.
            'max_seats_left' => $this->departures->max('seats_left'),
            'updated_at' => $this->updated_at,
        ];
    }
}