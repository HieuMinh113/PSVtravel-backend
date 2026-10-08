<?php

namespace App\Models;

use App\Services\ChamCong\CauHinhChamCong;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Chấm công một ngày của một nhân viên: phần "in" (vào) và "out" (ra), mỗi
 * phần có giờ, vị trí, ảnh, độ khớp khuôn mặt, lý do. Phút trễ / về sớm tự
 * tính lại mỗi lần lưu theo giờ làm trong Cấu hình chấm công.
 */
class Attendance extends Model
{
    use LogsActivity;

    /** Ảnh khuôn mặt là dữ liệu nhạy cảm → ổ riêng tư, xem qua link có chữ ký. */
    public const DIA = 'rieng';

    /** Khoảng cách 2 bộ đặc trưng khuôn mặt dưới mức này = cùng một người. */
    public const NGUONG_KHUON_MAT = 0.55;

    public const VI_TRI = [
        'trong' => 'Ở công ty',
        'ngoai' => 'Ngoài công ty',
        'khong_ro' => 'Không rõ vị trí',
    ];

    public const DUYET = [
        'cho_duyet' => 'Chờ duyệt',
        'chap_nhan' => 'Chấp nhận',
        'tu_choi' => 'Không chấp nhận',
    ];

    public const PHAN = ['in' => 'Vào', 'out' => 'Ra'];

    protected $guarded = ['id'];

    protected $casts = [
        'work_date' => 'date',
        'in_at' => 'datetime',
        'out_at' => 'datetime',
        'in_lat' => 'float',
        'in_lng' => 'float',
        'out_lat' => 'float',
        'out_lng' => 'float',
        'in_face_distance' => 'float',
        'out_face_distance' => 'float',
        'in_face_ok' => 'boolean',
        'out_face_ok' => 'boolean',
        'reviewed_at' => 'datetime',
        'late_minutes' => 'integer',
        'early_minutes' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(fn (Attendance $a) => $a->tinhPhut());
    }

    /**
     * Dòng chấm công của một người một ngày (chưa có thì tạo mới, chưa lưu).
     * Tìm bằng whereDate: SQLite lưu cột ngày kèm giờ "00:00:00".
     */
    public static function cuaNgay(int $userId, Carbon $ngay): self
    {
        return static::where('user_id', $userId)->whereDate('work_date', $ngay->toDateString())->first()
            ?? new static(['user_id' => $userId, 'work_date' => $ngay->toDateString()]);
    }

    /** Phút trễ / về sớm so với giờ làm của ngày đó (chủ nhật không tính). */
    public function tinhPhut(): void
    {
        $lich = CauHinhChamCong::lichNgay($this->work_date);
        $choPhep = CauHinhChamCong::phutChoPhep();
        $this->late_minutes = 0;
        $this->early_minutes = 0;
        if (! $lich) {
            return;
        }
        [$vao, $ra] = $lich;
        if ($this->in_at && $this->in_at->gt($vao->copy()->addMinutes($choPhep))) {
            $this->late_minutes = min(65535, (int) $vao->diffInMinutes($this->in_at));
        }
        if ($this->out_at && $this->out_at->lt($ra->copy()->subMinutes($choPhep))) {
            $this->early_minutes = min(65535, (int) $this->out_at->diffInMinutes($ra));
        }
    }

    /** Phần vào / ra được tính công: chấm thật, hoặc bổ sung đã được duyệt. */
    public function tinhPhan(string $p): bool
    {
        if (! $this->{"{$p}_at"}) {
            return false;
        }

        return $this->{"{$p}_source"} !== 'bo_sung' || $this->review_status === 'chap_nhan';
    }

    /** 1 công ngày thường, 0,5 công thứ 7, 0 chủ nhật / không chấm. */
    public function cong(): float
    {
        if (! $this->tinhPhan('in') && ! $this->tinhPhan('out')) {
            return 0;
        }

        return match ($this->work_date->dayOfWeek) {
            Carbon::SUNDAY => 0,
            Carbon::SATURDAY => 0.5,
            default => 1,
        };
    }

    /** Khuôn mặt không khớp, hoặc không thấy mặt trong ảnh. */
    public function nghiVan(?string $p = null): bool
    {
        foreach ($p ? [$p] : ['in', 'out'] as $x) {
            if ($this->{"{$x}_at"} && $this->{"{$x}_source"} === 'cham' && $this->{"{$x}_face_ok"} !== true) {
                return true;
            }
        }

        return false;
    }

    public function ngoaiCongTy(?string $p = null): bool
    {
        foreach ($p ? [$p] : ['in', 'out'] as $x) {
            if (in_array($this->{"{$x}_location"}, ['ngoai', 'khong_ro'], true)) {
                return true;
            }
        }

        return false;
    }

    /** Trễ / về sớm đã được quản lý chấp nhận lý do → không tính vi phạm. */
    public function coPhep(): bool
    {
        return $this->review_status === 'chap_nhan';
    }

    public function linkAnh(string $p): ?string
    {
        $duong = $this->{"{$p}_photo"};

        return $duong ? Storage::disk(self::DIA)->temporaryUrl($duong, now()->addMinutes(30)) : null;
    }

    public function linkBanDo(string $p): ?string
    {
        $lat = $this->{"{$p}_lat"};
        $lng = $this->{"{$p}_lng"};

        return $lat !== null && $lng !== null ? "https://www.google.com/maps?q={$lat},{$lng}" : null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function nguoiDuyet(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Không ghi toạ độ / ảnh vào nhật ký — chỉ giờ và việc duyệt
        return LogOptions::defaults()
            ->logOnly(['in_at', 'out_at', 'in_source', 'out_source', 'review_status', 'review_note'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('attendance');
    }
}
