<?php

namespace Modules\Guide\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Thành phố trên bản đồ cẩm nang — bấm vào thì bản đồ bay tới (lat, lng, zoom). */
class GuideCity extends Model
{
    protected $fillable = [
        'guide_country_id', 'name', 'slug', 'lat', 'lng', 'zoom', 'intro', 'cover_image', 'guide_id', 'status', 'sort_order',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'zoom' => 'float',
        'sort_order' => 'integer',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(GuideCountry::class, 'guide_country_id');
    }

    /** Bài tổng quan về thành phố (không bắt buộc). */
    public function guide(): BelongsTo
    {
        return $this->belongsTo(Guide::class);
    }

    public function spots(): HasMany
    {
        return $this->hasMany(GuideSpot::class)->orderBy('sort_order')->orderBy('name');
    }

    public function scopeDangHienThi($query)
    {
        return $query->where('status', 'published')->orderBy('sort_order')->orderBy('name');
    }
}
