<?php

namespace Modules\Tour\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một lượt quét mã QR của tour (xem App\Services\MaQrTour). */
class TourQrScan extends Model
{
    public $timestamps = false;

    protected $fillable = ['tour_id', 'thiet_bi', 'scanned_at'];

    protected $casts = ['scanned_at' => 'datetime'];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }
}
