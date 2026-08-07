<?php

namespace Modules\Tour\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
class TourDeparture extends Model
{
    use LogsActivity;
    protected $fillable = [
        'tour_id', 'start_date', 'price_override',
        'seats_total', 'seats_left', 'status',
    ];

    protected $casts = [
        'start_date'     => 'date',
        'price_override' => 'integer',
        'seats_total'    => 'integer',
        'seats_left'     => 'integer',
    ];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['start_date', 'seats_total', 'seats_left', 'price_override', 'status'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('tour_departure');
    }
}