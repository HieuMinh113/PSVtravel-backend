<?php

namespace Modules\Booking\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Payment extends Model
{
    use LogsActivity;

    protected $fillable = [
        'booking_id', 'method', 'amount', 'transaction_ref',
        'gateway_txn_id', 'status', 'received_by', 'note',
        'gateway_response', 'paid_at', 'proof_images',
    ];

    /** Ảnh chứng minh chuyển khoản lưu ở ổ riêng tư, xem qua link có chữ ký. */
    public const DIA_CHUNG_TU = 'rieng';

    public const TRANG_THAI = [
        'success' => 'Đã nhận tiền',
        'pending' => 'Chờ kế toán duyệt',
        'failed' => 'Từ chối / thất bại',
    ];

    protected $casts = [
        'amount' => 'integer',
        'gateway_response' => 'array',
        'paid_at' => 'datetime',
        'proof_images' => 'array',
        'approved_at' => 'datetime',
    ];

    // Mỗi lần ghi nhận thay đổi, tính lại trạng thái thanh toán của đơn
    protected static function booted(): void
    {
        static::saving(function (Payment $p) {
            // Ai chuyển khoản sang "Đã nhận tiền" thì người đó là người duyệt.
            // Cổng thanh toán tự báo (không ai đăng nhập) thì để trống.
            if ($p->isDirty('status') && $p->status === 'success' && auth()->check()) {
                $p->approved_by = auth()->id();
                $p->approved_at = now();
            }
            // Ảnh bị gỡ khỏi khoản thu → xoá file
            if ($p->exists && $p->isDirty('proof_images')) {
                $cu = (array) json_decode((string) $p->getRawOriginal('proof_images'), true);
                $bo = array_diff($cu, (array) $p->proof_images);
                $bo && Storage::disk(self::DIA_CHUNG_TU)->delete(array_values($bo));
            }
        });
        static::saved(fn (Payment $p) => $p->capNhatTrangThaiDon());
        static::deleted(function (Payment $p) {
            $p->capNhatTrangThaiDon();
            $p->proof_images && Storage::disk(self::DIA_CHUNG_TU)->delete($p->proof_images);
        });
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

        $booking->forceFill(['payment_status' => $trangThai, 'paid_total' => (int) $daThu])->saveQuietly();
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['amount', 'method', 'status', 'paid_at', 'approved_by'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('payment');
    }
}
