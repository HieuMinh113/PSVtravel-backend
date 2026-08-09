<?php

namespace Modules\Flight\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class FlightDeal extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'airline_id', 'from_city', 'to_city', 'trip_type',
        'price', 'old_price', 'valid_from', 'valid_to',
        'note', 'status', 'sort_order',
    ];

    protected $casts = [
        'price' => 'integer',
        'old_price' => 'integer',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'sort_order' => 'integer',
    ];

    public function airline(): BelongsTo
    {
        return $this->belongsTo(Airline::class);
    }

    // Chặng còn hiệu lực và đang bật
    public function scopeDangHienThi($query)
    {
        return $query->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', now()))
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', now()))
            ->orderBy('sort_order');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['from_city', 'to_city', 'price', 'status', 'valid_from', 'valid_to'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('flight_deal');
    }
}