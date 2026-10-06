<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

// Đánh giá của khách cho một gói sự kiện / team building.
class EventReview extends Model
{
    use SoftDeletes;

    public const TRANG_THAI = [
        'pending' => 'Chờ duyệt',
        'approved' => 'Đã duyệt',
        'rejected' => 'Đã từ chối',
    ];

    protected $fillable = [
        'event_id', 'customer_name', 'rating', 'content',
        'status', 'approved_by', 'approved_at', 'admin_reply', 'ip',
    ];

    protected $casts = [
        'rating' => 'integer',
        'approved_at' => 'datetime',
    ];

    // Mỗi khi đánh giá đổi, tính lại điểm trung bình của gói
    protected static function booted(): void
    {
        static::saved(fn (EventReview $r) => $r->capNhatDiemGoi());
        static::deleted(fn (EventReview $r) => $r->capNhatDiemGoi());
        static::restored(fn (EventReview $r) => $r->capNhatDiemGoi());
    }

    public function capNhatDiemGoi(): void
    {
        $event = $this->event;
        if (! $event) {
            return;
        }

        $daDuyet = static::query()
            ->where('event_id', $event->id)
            ->where('status', 'approved');

        $soLuot = (clone $daDuyet)->count();
        $diemTB = $soLuot > 0 ? round((clone $daDuyet)->avg('rating'), 1) : null;

        $event->updateQuietly([
            'rating' => $diemTB,
            'review_count' => $soLuot,
        ]);
    }

    public function scopeDaDuyet($query)
    {
        return $query->where('status', 'approved');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
