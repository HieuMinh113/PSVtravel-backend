<?php

namespace Modules\Booking\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Modules\Tour\Models\Tour;
use Modules\Tour\Models\TourDeparture;

class Booking extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'booking_code', 'tour_id', 'tour_departure_id', 'user_id',
        'customer_name', 'customer_phone', 'customer_email',
        'adults', 'children',
        'unit_price_adult', 'unit_price_child', 'total_price',
        'status', 'payment_status', 'note', 'admin_note',
        'cancelled_by', 'cancel_reason', 'cancelled_at',
    ];

    protected $casts = [
        'adults' => 'integer',
        'children' => 'integer',
        'unit_price_adult' => 'integer',
        'unit_price_child' => 'integer',
        'total_price' => 'integer',
        'cancelled_at' => 'datetime',
    ];

    // Tự sinh mã đơn nếu chưa có, vd PSV-20260801-A3F9
    protected static function booted(): void
    {
        static::creating(function (Booking $booking) {
            if (empty($booking->booking_code)) {
                $booking->booking_code = 'PSV-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));
            }
        });
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function departure(): BelongsTo
    {
        return $this->belongsTo(TourDeparture::class, 'tour_departure_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}