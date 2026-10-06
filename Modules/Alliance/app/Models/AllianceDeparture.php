<?php

namespace Modules\Alliance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một ngày khởi hành của tour liên minh, kèm số chỗ đọc từ sheet. */
class AllianceDeparture extends Model
{
    public const TRANG_THAI = [
        'con_cho' => 'Còn chỗ',
        'het_cho' => 'Hết chỗ',
        'tam_ngung' => 'Tạm ngưng nhận',
        'huy' => 'Huỷ đoàn',
        'lien_he' => 'Liên hệ đối tác',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'departure_date' => 'date',
        'price' => 'integer',
        'price_child' => 'integer',
        'price_infant' => 'integer',
        'commission' => 'integer',
        'seats_total' => 'integer',
        'seats_sold' => 'integer',
        'seats_hold' => 'integer',
        'seats_left' => 'integer',
        'sheet_row' => 'integer',
    ];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(AllianceTour::class, 'alliance_tour_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(AllianceSource::class, 'alliance_source_id');
    }

    /** Khách đặt được không: còn chỗ và chưa bị đối tác đóng. */
    public function conNhanKhach(): bool
    {
        return $this->status === 'con_cho' && (int) $this->seats_left > 0;
    }
}
