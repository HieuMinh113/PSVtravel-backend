<?php

namespace Modules\Maldives\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ngày khởi hành của combo + giá USD cho 1 người lớn theo từng hạng phòng:
 * prices = {room_type_id: giá}. Hạng phòng bỏ trống = ngày đó không bán hạng đó.
 */
class MaldivesComboDate extends Model
{
    public const TRANG_THAI = ['open' => 'Đang nhận khách', 'full' => 'Hết phòng', 'closed' => 'Đóng'];

    protected $fillable = ['combo_id', 'start_date', 'label', 'prices', 'status'];

    protected $casts = ['start_date' => 'date', 'prices' => 'array'];

    public function combo(): BelongsTo
    {
        return $this->belongsTo(MaldivesCombo::class, 'combo_id');
    }

    /** Ngày chưa qua (khách còn đặt / xem được) */
    public function scopeSapToi(Builder $q): Builder
    {
        return $q->whereDate('start_date', '>=', today());
    }

    /** Giá USD / người lớn của một hạng phòng; null = ngày này không bán hạng đó */
    public function gia(int|string|null $hangPhong): ?int
    {
        $g = ($this->prices ?? [])[(string) $hangPhong] ?? null;

        return is_numeric($g) && (int) $g > 0 ? (int) $g : null;
    }

    /** @return list<int> các giá đã nhập */
    public function cacGia(): array
    {
        return array_values(array_filter(array_map(fn ($g) => is_numeric($g) ? (int) $g : 0, $this->prices ?? []), fn ($g) => $g > 0));
    }
}
