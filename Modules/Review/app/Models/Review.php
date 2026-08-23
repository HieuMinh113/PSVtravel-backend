<?php

namespace Modules\Review\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Tour\Models\Tour;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Review extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'tour_id', 'user_id', 'booking_id', 'customer_name', 'rating', 'content',
        'status', 'approved_by', 'approved_at', 'admin_reply',
    ];

    protected $casts = [
        'rating' => 'integer',
        'approved_at' => 'datetime',
    ];

    // Mỗi khi đánh giá thay đổi, tính lại điểm trung bình của tour
    protected static function booted(): void
    {
        static::saved(fn (Review $review) => $review->capNhatDiemTour());
        static::deleted(fn (Review $review) => $review->capNhatDiemTour());
        static::restored(fn (Review $review) => $review->capNhatDiemTour());
    }

    public function capNhatDiemTour(): void
    {
        $tour = $this->tour;

        if (! $tour) {
            return;
        }

        $daDuyet = static::query()
            ->where('tour_id', $tour->id)
            ->where('status', 'approved');

        $soLuot = (clone $daDuyet)->count();
        $diemTB = $soLuot > 0 ? round((clone $daDuyet)->avg('rating'), 1) : null;

        $tour->updateQuietly([
            'rating' => $diemTB,
            'review_count' => $soLuot,
        ]);
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeDaDuyet($query)
    {
        return $query->where('status', 'approved');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'rating', 'admin_reply', 'customer_name'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('review');
    }
}