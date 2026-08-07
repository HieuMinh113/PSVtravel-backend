<?php

namespace Modules\Tour\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
class Tour extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'slug', 'name', 'type', 'region', 'country',
        'duration_days', 'duration_nights', 'departure_from',
        'adult_price', 'child_price', 'old_price',
        'tag', 'cover_image',
        'highlights', 'included', 'excluded',
        'cancellation_policy', 'description',
        'rating', 'review_count',
        'status', 'is_featured', 'sort_order',
    ];

    protected $casts = [
        'highlights'   => 'array',
        'included'     => 'array',
        'excluded'     => 'array',
        'adult_price'  => 'integer',
        'child_price'  => 'integer',
        'old_price'    => 'integer',
        'rating'       => 'decimal:1',
        'review_count' => 'integer',
        'is_featured'  => 'boolean',
        'sort_order'   => 'integer',
    ];

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
        return $this->hasMany(TourImage::class)->orderBy('sort_order');
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
}