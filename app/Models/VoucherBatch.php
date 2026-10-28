<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Một đợt voucher: mệnh giá, hạn dùng, điều kiện, ảnh 2 mặt (thiết kế riêng
 * từng đợt) và vị trí ô mã + QR trên ảnh. Mã voucher sinh theo đợt.
 */
class VoucherBatch extends Model
{
    use LogsActivity, SoftDeletes;

    public const TRANG_THAI = ['active' => 'Đang phát hành', 'paused' => 'Tạm ngưng'];

    public const GOC = [
        'bottom_right' => 'Góc dưới bên phải',
        'bottom_left' => 'Góc dưới bên trái',
        'top_right' => 'Góc trên bên phải',
        'top_left' => 'Góc trên bên trái',
    ];

    public const MAT = ['front' => 'Mặt trước', 'back' => 'Mặt sau'];

    /** Số mã tối đa mỗi lần tạo */
    public const TOI_DA_MOI_LAN = 1000;

    protected $fillable = [
        'name', 'code_prefix', 'value', 'expires_at', 'applies_to', 'conditions', 'front_image', 'back_image',
        'code_side', 'code_corner', 'code_margin_mm', 'code_size_mm', 'show_barcode', 'status', 'note', 'created_by',
    ];

    protected $casts = [
        'value' => 'integer',
        'expires_at' => 'date',
        'code_margin_mm' => 'float',
        'code_size_mm' => 'float',
        'show_barcode' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $d) {
            $d->created_by ??= auth()->id();
            $d->code_prefix = self::tienTo($d->code_prefix);
        });
        static::updating(function (self $d) {
            $d->code_prefix = self::tienTo($d->code_prefix);
        });
    }

    /** Tiền tố mã: 2–5 chữ in hoa / số, mặc định PSV */
    public static function tienTo(?string $s): string
    {
        $s = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $s));

        return strlen($s) >= 2 ? substr($s, 0, 5) : 'PSV';
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class, 'batch_id');
    }

    public function nguoiTao(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Hết hạn: qua hết ngày ghi trên voucher (giờ Việt Nam) */
    public function hetHan(): bool
    {
        return $this->expires_at !== null && $this->expires_at->copy()->endOfDay()->isPast();
    }

    /**
     * Sinh thêm $soLuong mã (dùng một lần). Số seri nối tiếp các mã đã có.
     *
     * @return int seri đầu tiên của lần tạo này
     */
    public function taoMa(int $soLuong, ?string $phatCho = null): int
    {
        $soLuong = max(1, min(self::TOI_DA_MOI_LAN, $soLuong));

        return DB::transaction(function () use ($soLuong, $phatCho) {
            // khoá đợt để hai người bấm "Tạo mã" cùng lúc không trùng số seri
            self::query()->whereKey($this->id)->lockForUpdate()->first();
            $dau = (int) $this->vouchers()->max('serial') + 1;
            $bayGio = now();
            $hang = [];
            $daCo = [];
            for ($i = 0; $i < $soLuong; $i++) {
                do {
                    $ma = Voucher::maMoi($this->code_prefix);
                } while (isset($daCo[$ma]) || Voucher::query()->where('code', $ma)->exists());
                $daCo[$ma] = true;
                $hang[] = [
                    'batch_id' => $this->id, 'serial' => $dau + $i, 'code' => $ma, 'lookup_key' => Voucher::khoa($ma),
                    'status' => 'unused', 'issued_to' => $phatCho ?: null, 'created_at' => $bayGio, 'updated_at' => $bayGio,
                ];
            }
            foreach (array_chunk($hang, 500) as $phan) {
                Voucher::query()->insert($phan);
            }
            activity('voucher')->performedOn($this)->event('tao_ma')
                ->withProperties(['so_luong' => $soLuong, 'seri_tu' => $dau, 'seri_den' => $dau + $soLuong - 1])
                ->log("Tạo {$soLuong} mã voucher");

            return $dau;
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'value', 'expires_at', 'status'])->logOnlyDirty()->dontLogEmptyChanges()->useLogName('voucher');
    }
}
