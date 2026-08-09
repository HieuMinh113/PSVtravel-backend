<?php

namespace Modules\Flight\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Airline extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'logo', 'country', 'website', 'note', 'status', 'sort_order',
    ];

    protected $casts = ['sort_order' => 'integer'];

    public function deals(): HasMany
    {
        return $this->hasMany(FlightDeal::class);
    }

    public function scopeDangHienThi($query)
    {
        return $query->where('status', 'published')->orderBy('sort_order');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'name', 'status', 'sort_order'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('airline');
    }
}