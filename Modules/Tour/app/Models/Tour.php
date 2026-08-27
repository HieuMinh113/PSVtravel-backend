<?php

namespace Modules\Tour\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Category\Models\Category;
class Tour extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'slug', 'name', 'type', 'region', 'country',
        'duration_days', 'duration_nights', 'departure_from',
        'adult_price', 'child_price', 'old_price',
        'tag', 'cover_image',
        'highlights', 'included', 'excluded',
        'cancellation_policy', 'notes', 'description',
        'rating', 'review_count',
        'status', 'is_featured', 'sort_order',
    ];

    protected $casts = [
        'highlights'   => 'array',
        'included'     => 'array',
        'excluded'     => 'array',
        'notes'        => 'array',
        'adult_price'  => 'integer',
        'child_price'  => 'integer',
        'old_price'    => 'integer',
        'rating'       => 'decimal:1',
        'review_count' => 'integer',
        'is_featured'  => 'boolean',
        'sort_order'   => 'integer',
    ];

    /**
     * Tách một danh sách (bao gồm / không bao gồm) thành từng mục riêng.
     *
     * Dùng ở CẢ HAI đầu:
     *  - lúc lưu trong admin, để dán nguyên đoạn từ file chương trình tour vào
     *    là ra đúng từng mục;
     *  - lúc trả về cho website, để những tour đã nhập từ trước — cả đoạn văn
     *    dồn thành MỘT mục có dấu ➢ ở giữa — hiện ra đúng, khỏi phải mở từng
     *    tour ra lưu lại.
     */
    public static function tachTungMuc($gia): array
    {
        if (is_array($gia)) {
            $gia = implode("\n", $gia);
        }

        return collect(preg_split('/[\r\n➢▪•]+/u', (string) $gia))
            // Cắt khoảng trắng và dấu gạch đầu dòng, GIỮ dấu chấm cuối câu.
            ->map(fn ($dong) => trim($dong, " \t\u{00A0}-"))
            ->filter()
            ->values()
            ->all();
    }

    // Đơn đặt của tour — dùng để chặn xoá vĩnh viễn khi tour đã phát sinh giao dịch
    public function bookings(): HasMany
    {
        return $this->hasMany(\Modules\Booking\Models\Booking::class);
    }

    public function departures(): HasMany
    {
        return $this->hasMany(TourDeparture::class);
    }

    public function itineraries(): HasMany
    {
        return $this->hasMany(TourItinerary::class)->orderBy('day_number');
    }

    public function images(): HasMany
    {
        // Thêm id làm tiêu chí phụ: nhiều ảnh cùng "Thứ tự = 0" thì Postgres trả
        // về theo thứ tự ngẫu nhiên, mỗi lần tải trang một khác.
        return $this->hasMany(TourImage::class)->orderBy('sort_order')->orderBy('id');
    }

    // Chỉ lấy tour đang hiển thị công khai
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name', 'slug', 'status', 'adult_price', 'child_price',
                'old_price', 'is_featured', 'type',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('tour');
    }
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_tour');
    }
    public function reviews(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\Modules\Review\Models\Review::class);
    }
}