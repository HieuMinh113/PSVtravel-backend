<?php

namespace Modules\Maldives\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Đơn đặt combo Maldives. Giá trên đơn là giá TẠM TÍNH lúc khách đặt (USD +
 * tỷ giá khi đó); nhân viên gọi xác nhận còn phòng rồi chốt số tiền VNĐ.
 */
class MaldivesBooking extends Model
{
    use LogsActivity, SoftDeletes;

    public const TRANG_THAI = [
        'pending' => 'Mới — chưa ai xử lý',
        'contacting' => 'Đang tư vấn',
        'confirmed' => 'Đã xác nhận còn phòng',
        'deposited' => 'Đã đặt cọc',
        'completed' => 'Hoàn tất',
        'cancelled' => 'Đã huỷ',
    ];

    public const NGUON = ['web' => 'Khách đặt web', 'quan_tri' => 'Nhân viên tạo'];

    protected $fillable = [
        'combo_id', 'combo_date_id', 'room_type_id', 'combo_name', 'start_date', 'room_type_name',
        'departure_city', 'surcharge_usd', 'adults', 'children', 'infants', 'rooms',
        'unit_price_usd', 'child_percent', 'total_usd', 'exchange_rate', 'total_vnd',
        'customer_name', 'customer_phone', 'customer_email', 'note',
        'status', 'assigned_to', 'paid_vnd', 'admin_note',
    ];

    protected $casts = [
        'start_date' => 'date',
        'surcharge_usd' => 'integer',
        'adults' => 'integer',
        'children' => 'integer',
        'infants' => 'integer',
        'rooms' => 'integer',
        'unit_price_usd' => 'integer',
        'child_percent' => 'integer',
        'total_usd' => 'integer',
        'exchange_rate' => 'integer',
        'total_vnd' => 'integer',
        'paid_vnd' => 'integer',
        'utm' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $b) {
            $b->code ??= 'MDV-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));
            // Nhân viên tạo đơn trong trang quản trị = người phụ trách
            if ($b->source !== 'web') {
                $b->assigned_to ??= auth()->id();
            }
        });
    }

    public function combo(): BelongsTo
    {
        return $this->belongsTo(MaldivesCombo::class, 'combo_id')->withTrashed();
    }

    public function comboDate(): BelongsTo
    {
        return $this->belongsTo(MaldivesComboDate::class, 'combo_date_id');
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(MaldivesRoomType::class, 'room_type_id');
    }

    public function nguoiPhuTrach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'assigned_to', 'paid_vnd', 'admin_note', 'total_usd'])
            ->logOnlyDirty()->dontLogEmptyChanges()->useLogName('maldives_booking');
    }
}
