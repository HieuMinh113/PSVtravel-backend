<?php

namespace Modules\Alliance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Tour\Models\Tour;

/** Một tour đọc được từ sheet đối tác (máy tự tạo / cập nhật). */
class AllianceTour extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(AllianceSource::class, 'alliance_source_id');
    }

    public function departures(): HasMany
    {
        return $this->hasMany(AllianceDeparture::class);
    }

    /** Các tour PSV đang lấy số chỗ theo tour liên minh này. */
    public function psvTours(): HasMany
    {
        return $this->hasMany(Tour::class, 'alliance_tour_id');
    }

    /**
     * Hãng / chuyến bay ngắn gọn để hiện cạnh tên tour: "VNA", "Vietjet Air",
     * hoặc số hiệu chuyến "VJ862" (sheet M Tour ghi "Chuyến đi⏎VJ862 HCM - INC…").
     */
    public function hangNgan(): ?string
    {
        if (! $this->airline) {
            return null;
        }
        foreach (explode("\n", $this->airline) as $dong) {
            $dong = trim($dong);
            if ($dong === '' || preg_match('/^chuy[ếe]n (đi|về)\s*:?$/iu', $dong)) {
                continue;
            }
            if (preg_match('/^\s*([A-Z0-9]{2})\s?(\d{3,4})\b/', $dong, $m)) {
                return $m[1].$m[2];
            }

            return mb_strimwidth($dong, 0, 30, '…');
        }

        return null;
    }

    /** Nhãn dùng trong ô chọn: "Tên tour — Đối tác (hãng bay)". */
    public function nhan(): string
    {
        $hang = $this->hangNgan();

        return $this->name.' — '.($this->source?->name ?? '?').($hang ? " ({$hang})" : '');
    }
}
