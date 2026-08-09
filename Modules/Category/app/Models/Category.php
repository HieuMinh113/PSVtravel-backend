<?php

namespace Modules\Category\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Tour\Models\Tour;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Category extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'type', 'name', 'slug', 'description', 'image', 'status', 'sort_order',
    ];

    protected $casts = ['sort_order' => 'integer'];

    public function tours(): BelongsToMany
    {
        return $this->belongsToMany(Tour::class, 'category_tour');
    }

    public function scopeDangHienThi($query)
    {
        return $query->where('status', 'published')->orderBy('sort_order');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug', 'type', 'status', 'sort_order'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('category');
    }
}