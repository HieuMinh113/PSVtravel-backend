<?php

namespace Modules\Moment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Tour\Models\Tour;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Moment extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'image', 'caption', 'customer_name', 'tour_id', 'status', 'sort_order',
    ];

    protected $casts = ['sort_order' => 'integer'];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function scopeDangHienThi($query)
    {
        return $query->where('status', 'published')->orderBy('sort_order');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['caption', 'customer_name', 'status', 'sort_order'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('moment');
    }
}