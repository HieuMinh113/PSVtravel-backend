<?php

namespace Modules\Booking\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Payment extends Model
{
    use LogsActivity;

    protected $fillable = [
        'booking_id', 'method', 'amount', 'transaction_ref',
        'gateway_txn_id', 'status', 'received_by', 'note',
        'gateway_response', 'paid_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'gateway_response' => 'array',
        'paid_at' => 'datetime',
    ];

    // Mỗi lần ghi nhận thay đổi, tính lại trạng thái thanh toán của đơn
    protected static function booted(): void
    {
        static::saved(fn (Payment $p) => $p->capNhatTrangThaiDon());
        static::deleted(fn (Payment $p) => $p->capNhatTrangThaiDon());
    }

    public function capNhatTrangThaiDon(): void
    {
        $booking = $this->booking;

        if (! $booking) {
            return;
        }

        $daThu = static::query()
            ->where('booking_id', $booking->id)
            ->where('status', 'success')
            ->sum('amount');

        $trangThai = match (true) {
            $daThu <= 0 => 'unpaid',
            $daThu >= $booking->total_price => 'paid',
            default => 'partial',
        };

        $booking->updateQuietly(['payment_status' => $trangThai]);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['amount', 'method', 'status', 'paid_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('payment');
    }
}