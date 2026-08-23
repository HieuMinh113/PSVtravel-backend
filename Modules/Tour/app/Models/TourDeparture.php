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

    /**
     * Trạng thái luôn khớp với số chỗ còn lại.
     *
     * Trước đây sửa tay số chỗ từ 0 lên 30 mà trạng thái vẫn nằm ở "Hết chỗ" —
     * đợt có chỗ nhưng khách không đặt được, mà nhân viên không hiểu vì sao.
     *
     * Ngoại lệ "closed": người quản trị chủ động đóng đợt (đủ đoàn, huỷ chuyến...)
     * thì tôn trọng, không tự mở lại chỉ vì còn chỗ trống.
     */
    protected static function booted(): void
    {
        static::saving(function (TourDeparture $dot) {
            if ($dot->status === 'closed') {
                return;
            }

            $dot->status = $dot->seats_left > 0 ? 'open' : 'full';
        });
    }

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