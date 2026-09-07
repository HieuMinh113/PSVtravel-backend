<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
        'includes', 'group_size', 'duration', 'location', 'price_note',
        'is_featured', 'status', 'sort_order',
    ];

    protected $casts = [
        'audience' => 'array',
        'gallery' => 'array',
        'includes' => 'array',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
    ];

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
