<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

// Gói sự kiện / team building. Đặt ở app\Models (không phải module) để đi theo
// đúng lối của ContactMessage — khỏi phải đăng ký module/composer cho một tính
// năng gọn.
class Event extends Model
{
    use LogsActivity, SoftDeletes;

    // Đối tượng phù hợp của gói
    public const DOI_TUONG = [
        'gia-dinh' => 'Gia đình',
        'doanh-nghiep' => 'Doanh nghiệp',
        'ca-nhan' => 'Cá nhân',
    ];

    protected $fillable = [
        'title', 'slug', 'summary', 'audience', 'cover_image', 'gallery', 'description',
        'includes', 'itinerary', 'group_size', 'duration', 'location', 'price_note',
        'is_featured', 'status', 'sort_order',
        // rating/review_count KHÔNG nằm ở đây: chỉ EventReview tự cập nhật qua
        // updateQuietly, admin không nhập tay.
    ];

    protected $casts = [
        'audience' => 'array',
        'gallery' => 'array',
        'includes' => 'array',
        'itinerary' => 'array',
        'rating' => 'float',
        'review_count' => 'integer',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function reviews(): HasMany
    {
        return $this->hasMany(EventReview::class);
    }

    public function scopeDangHienThi($query)
    {
        return $query->where('status', 'published');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'slug', 'status', 'is_featured', 'sort_order'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('event');
    }
}
