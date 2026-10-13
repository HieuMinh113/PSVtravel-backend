<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Hồ sơ ứng tuyển gửi từ trang chi tiết tin tuyển dụng (POST /api/v1/jobs/{slug}/apply).
 *
 * CV / portfolio nằm ở ổ riêng tư "rieng" (thư mục ung-tuyen) — chỉ mở được
 * qua route quản trị có kiểm tra quyền (TaiTepUngTuyenController). Xoá hồ sơ
 * là xoá luôn file: CV là dữ liệu cá nhân, không giữ lại khi đã bỏ.
 */
class JobApplication extends Model
{
    public const DIA = 'rieng';

    public const THU_MUC = 'ung-tuyen';

    public const TRANG_THAI = [
        'moi' => 'Mới',
        'da_lien_he' => 'Đã liên hệ',
        'phong_van' => 'Hẹn phỏng vấn',
        'da_tuyen' => 'Đã tuyển',
        'khong_phu_hop' => 'Chưa phù hợp',
    ];

    public const MAU_TRANG_THAI = [
        'moi' => 'danger',
        'da_lien_he' => 'info',
        'phong_van' => 'warning',
        'da_tuyen' => 'success',
        'khong_phu_hop' => 'gray',
    ];

    // Trang quản trị chỉ sửa được trạng thái và ghi chú; phần còn lại do ứng viên gửi
    protected $fillable = ['status', 'admin_note'];

    protected $casts = [
        'handled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleted(function (JobApplication $hs) {
            Storage::disk(self::DIA)->delete(array_filter([$hs->cv_path, $hs->portfolio_path]));
        });
    }

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class)->withTrashed();
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /** Link mở CV / portfolio trong trang quản trị (kiểm tra quyền mỗi lần mở). */
    public function linkTep(string $loai): ?string
    {
        $coTep = $loai === 'cv' ? $this->cv_path : $this->portfolio_path;

        return $coTep ? route('filament.admin.ung-tuyen.tep', ['hoSo' => $this, 'loai' => $loai]) : null;
    }
}
