<?php

namespace Modules\Booking\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Tour\Models\Tour;
use Modules\Tour\Models\TourDeparture;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
class Booking extends Model
{
    use LogsActivity, SoftDeletes;


    protected $fillable = [
        'booking_code', 'tour_id', 'tour_departure_id', 'user_id',
        'customer_name', 'customer_phone', 'customer_email',
        'adults', 'children',
        'unit_price_adult', 'unit_price_child', 'total_price',
        'status', 'payment_status', 'note', 'admin_note',
        'cancelled_by', 'cancel_reason', 'cancelled_at',
        'deposit_percent', 'remind_on',
    ];

    /** Nhắc khách đóng phần còn lại trước ngày đi bao nhiêu ngày. */
    public const NHAC_TRUOC_NGAY = 7;

    /**
     * Đơn có trẻ em nhưng tour chưa có giá trẻ em → tổng tiền CHƯA gồm trẻ em,
     * nhân viên cần báo giá thêm cho khách.
     */
    public function choBaoGiaTreEm(): bool
    {
        return (int) $this->children > 0 && $this->unit_price_child === null;
    }

    protected $casts = [
        'adults' => 'integer',
        'children' => 'integer',
        'unit_price_adult' => 'integer',
        'unit_price_child' => 'integer',
        'total_price' => 'integer',
        'cancelled_at' => 'datetime',
        'deposit_percent' => 'float',
        'deposit_amount' => 'integer',
        'remind_on' => 'date',
        'reminded_at' => 'datetime',
        'remind_notified_at' => 'datetime',
    ];

    // Tự sinh mã đơn nếu chưa có, vd PSV-20260801-A3F9
    protected static function booted(): void
    {
        static::creating(function (Booking $booking) {
            if (empty($booking->booking_code)) {
                $booking->booking_code = 'PSV-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));
            }
            // Nhân viên tạo đơn trong trang quản trị = người nhận chuông nhắc
            // khách. Đơn khách tự đặt trên web: người bấm Xác nhận sẽ nhận.
            $booking->created_by ??= auth()->id();
        });

        static::saving(function (Booking $booking) {
            // Tiền cọc = tổng × tỷ lệ, làm tròn nghìn đồng
            $booking->deposit_amount = $booking->deposit_percent
                ? (int) (round($booking->total_price * $booking->deposit_percent / 100 / 1000) * 1000)
                : null;

            // Hạn nhắn khách: tự đặt = ngày đi − 7 khi chưa có hoặc khi đổi đợt
            // khởi hành; nhân viên tự sửa ngày (cùng lần lưu) thì giữ ngày đó.
            $doiDot = $booking->isDirty('tour_departure_id');
            if (($booking->remind_on === null || $doiDot) && ! $booking->isDirty('remind_on') && $booking->tour_departure_id) {
                $ngayDi = TourDeparture::whereKey($booking->tour_departure_id)->value('start_date');
                $booking->remind_on = $ngayDi ? Carbon::parse($ngayDi)->subDays(self::NHAC_TRUOC_NGAY) : null;
            }
            // Đổi hạn → được báo chuông lại theo ngày mới
            if ($booking->isDirty('remind_on')) {
                $booking->remind_notified_at = null;
            }
        });
    }

    /** Tổng tiền đã thu (khoản thanh toán thành công). */
    public function daThu(): int
    {
        return (int) $this->payments()->where('status', 'success')->sum('amount');
    }

    public function conLai(): int
    {
        return max(0, (int) $this->total_price - $this->daThu());
    }

    /** null = đơn chưa đặt tỷ lệ cọc. */
    public function duCoc(): ?bool
    {
        return $this->deposit_amount ? $this->daThu() >= $this->deposit_amount : null;
    }

    /** Đến / quá hạn nhắn khách mà chưa nhắn (đơn còn chạy, chưa trả đủ). */
    public function canNhacKhach(): bool
    {
        return $this->remind_on !== null
            && $this->reminded_at === null
            && $this->remind_on->lte(today())
            && in_array($this->status, ['pending', 'confirmed'], true)
            && $this->payment_status !== 'paid';
    }

    public function scopeToiHanNhac($query)
    {
        return $query->whereNotNull('remind_on')
            ->whereNull('reminded_at')
            ->whereDate('remind_on', '<=', today())
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('payment_status', '!=', 'paid');
    }

    /** Tin nhắn Zalo nhắc khách đóng phần còn lại. */
    public function tinNhanNhacTien(?string $chuyenKhoan = null): string
    {
        $tien = fn (int $v) => number_format($v, 0, ',', '.').'đ';
        $ngayDi = $this->departure?->start_date?->format('d/m/Y');
        $daThu = $this->daThu();

        $dong = [
            "Chào anh/chị {$this->customer_name}, PSV Travel xin nhắc thanh toán cho đơn {$this->booking_code}:",
            '- Tour: '.($this->tour?->name ?? '').($ngayDi ? " — khởi hành {$ngayDi}" : ''),
            '- Số khách: '.$this->adults.' người lớn'.($this->children > 0 ? ', '.$this->children.' trẻ em' : ''),
            '- Tổng tiền: '.$tien((int) $this->total_price),
            '- Đã thanh toán: '.$tien($daThu),
        ];
        if ($this->deposit_amount && $daThu < $this->deposit_amount) {
            $dong[] = '- Tiền cọc ('.self::phanTram($this->deposit_percent).'): '.$tien($this->deposit_amount).' — còn thiếu '.$tien($this->deposit_amount - $daThu);
        }
        $dong[] = '- Còn lại cần thanh toán: '.$tien($this->conLai());
        if ($this->remind_on) {
            $dong[] = 'Anh/chị vui lòng hoàn tất trước ngày '.$this->remind_on->format('d/m/Y').' để PSV Travel giữ chỗ và chuẩn bị chuyến đi.';
        }
        if (filled($chuyenKhoan)) {
            $dong[] = "Thông tin chuyển khoản:\n".trim($chuyenKhoan);
            $dong[] = 'Nội dung chuyển khoản: '.$this->booking_code;
        }
        $dong[] = 'Cảm ơn anh/chị!';

        return implode("\n", $dong);
    }

    public static function phanTram(?float $v): string
    {
        return $v === null ? '' : rtrim(rtrim(number_format($v, 2, ',', ''), '0'), ',').'%';
    }

    public function nguoiTao(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function nguoiNhan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reminded_by');
    }

    // withTrashed: tour bị xoá mềm thì đơn cũ vẫn phải giữ được tên tour.
    // Không có nó, xoá một tour đang có đơn là mọi đơn liên quan hiện trống
    // trơn ở cột Tour — nhân viên không còn biết khách đã đặt cái gì.
    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class)->withTrashed();
    }

    public function departure(): BelongsTo
    {
        return $this->belongsTo(TourDeparture::class, 'tour_departure_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status', 'payment_status', 'total_price',
                'tour_departure_id', 'adults', 'children',
                'customer_name', 'customer_phone', 'cancel_reason',
                'deposit_percent', 'deposit_amount', 'remind_on', 'reminded_at',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('booking');
    }
}