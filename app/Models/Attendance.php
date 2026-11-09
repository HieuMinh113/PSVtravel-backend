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

    /** Vì sao lượt chấm không có vị trí / vị trí không dùng được. */
    public const VI_TRI_LOI = [
        'chua_cho_phep' => 'trình duyệt chưa được cho phép lấy vị trí',
        'het_gio' => 'chờ GPS quá lâu (trong nhà, sóng yếu)',
        'khong_lay_duoc' => 'máy không lấy được vị trí (đang tắt Vị trí / GPS)',
        'khong_ho_tro' => 'trình duyệt không hỗ trợ định vị',
        'sai_so_lon' => 'vị trí sai số quá lớn (máy tính đoán vị trí qua mạng)',
    ];

    /** Bản ngắn cho bảng chấm công. */
    public const VI_TRI_LOI_NGAN = [
        'chua_cho_phep' => 'chưa cho phép',
        'het_gio' => 'chờ GPS lâu',
        'khong_lay_duoc' => 'tắt định vị',
        'khong_ho_tro' => 'không hỗ trợ',
        'sai_so_lon' => 'sai số lớn',
    ];

    public const THIET_BI = [
        'dien_thoai' => 'Điện thoại',
        'may_tinh' => 'Máy tính',
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
        'in_scan_ms' => 'integer',
        'out_scan_ms' => 'integer',
    ];

    protected static function booted(): void
    {
        // Chốt phút trễ / về sớm lúc chấm (theo cấu hình lúc đó): chỉ tính lại phần
        // giờ vào / ra vừa đổi. Lưu dòng vì việc khác (chấm ra, duyệt…) hoặc đổi
        // cấu hình sau đó không làm đổi phần đã chốt.
        static::saving(fn (Attendance $a) => $a->tinhPhut(
            ! $a->exists || $a->isDirty('in_at') || $a->isDirty('work_date'),
            ! $a->exists || $a->isDirty('out_at') || $a->isDirty('work_date'),
        ));
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

    /**
     * Phút trễ / về sớm so với giờ làm của ngày đó (chủ nhật không tính), tính
     * theo phút như giờ hiển thị. $vao / $ra: tính lại phần nào.
     */
    public function tinhPhut(bool $vao = true, bool $ra = true): void
    {
        $lich = $this->work_date ? CauHinhChamCong::lichNgay($this->work_date) : null;
        if ($vao) {
            $this->late_minutes = $lich && $this->in_at ? min(65535, CauHinhChamCong::phutTre($this->in_at, $lich[0])) : 0;
        }
        if ($ra) {
            $this->early_minutes = $lich && $this->out_at ? min(65535, CauHinhChamCong::phutSom($this->out_at, $lich[1])) : 0;
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

    /**
     * 1 công ngày thường, 0,5 công thứ 7, 0 chủ nhật / không chấm. Chỉ được
     * nửa số đó (0,5 ngày thường, 0,25 thứ 7) khi:
     * - quên chấm vào (chỉ có giờ ra) và chưa có giờ vào bổ sung được duyệt —
     *   không thì cứ bỏ chấm vào là khỏi bị tính đi trễ;
     * - về sớm (quá số phút không tính) mà lý do chưa được quản lý chấp nhận;
     * - quên chấm ra (ngày đã qua) và chưa có giờ ra bổ sung được duyệt —
     *   không thì cứ không chấm ra là khỏi bị tính về sớm.
     */
    public function cong(): float
    {
        if (! $this->tinhPhan('in') && ! $this->tinhPhan('out')) {
            return 0;
        }
        $du = match ($this->work_date->dayOfWeek) {
            Carbon::SUNDAY => 0.0,
            Carbon::SATURDAY => 0.5,
            default => 1.0,
        };
        if ($du > 0 && $this->nuaCong()) {
            return $du / 2;
        }

        return $du;
    }

    /** Vì sao ngày này chỉ được nửa công: 'thieu_vao' | 've_som' | 'thieu_ra' | null (đủ công). */
    public function nuaCong(): ?string
    {
        $vao = $this->tinhPhan('in');
        $ra = $this->tinhPhan('out');

        return match (true) {
            ! $vao && ! $ra => null, // không chấm gì: 0 công chứ không phải nửa công
            ! $vao => 'thieu_vao',
            $ra => $this->early_minutes > 0 && ! $this->coPhep() ? 've_som' : null,
            default => $this->work_date->lt(today()) ? 'thieu_ra' : null, // hôm nay: chưa tới lúc về
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
