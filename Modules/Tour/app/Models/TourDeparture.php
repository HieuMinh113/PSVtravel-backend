<?php

namespace Modules\Tour\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class TourDeparture extends Model
{
    use LogsActivity;

    protected $fillable = [
        'tour_id', 'start_date', 'visa_deadline', 'price_override',
        'child_price_override', 'infant_price_override',
        'seats_total', 'seats_left', 'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'visa_deadline' => 'date',
        'price_override' => 'integer',
        'child_price_override' => 'integer',
        'infant_price_override' => 'integer',
        'alliance_sync' => 'array',
        'seats_total' => 'integer',
        'seats_left' => 'integer',
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

    /**
     * Hạn nộp hồ sơ visa của đợt này: ngày riêng của đợt nếu có, không thì
     * theo quy tắc chung của tour "trước ngày khởi hành N ngày". Null = tour
     * không đặt hạn (thường là tour không cần visa).
     */
    public function hanVisa(?Tour $tour = null): ?Carbon
    {
        if ($this->visa_deadline) {
            return $this->visa_deadline;
        }
        $soNgay = (int) ($tour ?? $this->tour)?->visa_deadline_days;

        return $soNgay > 0 && $this->start_date ? $this->start_date->copy()->subDays($soNgay) : null;
    }

    /** Hạn visa còn bao nhiêu ngày (âm = đã qua hạn). */
    public static function conNgay(?Carbon $han): ?int
    {
        return $han ? (int) today()->diffInDays($han->copy()->startOfDay(), false) : null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['start_date', 'visa_deadline', 'seats_total', 'seats_left', 'price_override', 'child_price_override', 'infant_price_override', 'status'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('tour_departure');
    }
}
