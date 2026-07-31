<?php

namespace Modules\Tour\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourDeparture extends Model
{
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
}