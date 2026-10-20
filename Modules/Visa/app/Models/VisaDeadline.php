<?php

namespace Modules\Visa\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Tour\Services\HanNopVisa;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Hạn nộp hồ sơ visa theo nước (nhân viên visa quản lý): "Trung Quốc — 7 ngày"
 * nghĩa là ngày khởi hành 22 thì từ ngày 15 (22 − 7) đợt đó tự ẩn trên website,
 * khách chỉ còn nộp hồ sơ / đặt tour tới hết ngày 14.
 */
class VisaDeadline extends Model
{
    use LogsActivity;

    protected $fillable = ['country', 'days', 'note'];

    protected $casts = [
        'days' => 'integer',
    ];

    /** Đổi danh sách → tính lại ngày ẩn của mọi đợt khởi hành tour nước ngoài. */
    protected static function booted(): void
    {
        static::saved(fn () => HanNopVisa::tinhLaiTatCa());
        static::deleted(fn () => HanNopVisa::tinhLaiTatCa());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['country', 'days', 'note'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('visa_deadline');
    }
}
