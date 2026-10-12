<?php

namespace Modules\Guide\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Tour\Models\Tour;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Guide extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'title', 'seo_title', 'slug', 'excerpt', 'content', 'cover_image', 'video_url',
        'author_id', 'category', 'tour_id', 'view_count', 'status', 'published_at', 'sort_order',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'view_count' => 'integer',
        'sort_order' => 'integer',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    /** Các điểm trên bản đồ cẩm nang mở ra bài này. */
    public function spots(): HasMany
    {
        return $this->hasMany(GuideSpot::class);
    }

    /** Thành phố trên bản đồ dùng bài này làm bài tổng quan. */
    public function mapCities(): HasMany
    {
        return $this->hasMany(GuideCity::class);
    }

    public function scopeDangHienThi($query)
    {
        return $query->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'slug', 'status', 'published_at', 'category'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('guide');
    }
}