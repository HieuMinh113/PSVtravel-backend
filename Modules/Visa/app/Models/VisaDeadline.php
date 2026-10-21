<?php

namespace Modules\Visa\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Tour\Services\HanNopVisa;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Hạn nộp hồ sơ visa theo nước (nhân viên visa quản lý): "Trung Quốc — visa đoàn
 * 7 ngày" nghĩa là ngày khởi hành 22 thì từ ngày 15 (22 − 7) đợt đó tự ẩn trên
 * website, khách chỉ còn nộp hồ sơ / đặt tour tới hết ngày 14.
 *
 * Mỗi nước có thể có nhiều dòng, mỗi dạng visa một dòng.
 */
class VisaDeadline extends Model
{
    use LogsActivity;

    /** Dạng visa (tên trong admin). */
    public const DANG = [
        'doan' => 'Visa đoàn',
        'le' => 'Visa lẻ (dán hộ chiếu)',
        'dien_tu' => 'Visa điện tử (e-visa)',
        'thong_hanh' => 'Giấy thông hành',
    ];

    /** Giải thích ngắn cho khách trên trang tour. */
    public const GHI_CHU_DANG = [
        'doan' => 'đi và về cùng đoàn',
        'le' => 'visa cá nhân dán vào hộ chiếu',
        'dien_tu' => 'nhận qua email, không dán vào hộ chiếu',
        'thong_hanh' => 'dùng khi qua cửa khẩu đường bộ',
    ];

    protected $fillable = ['country', 'kind', 'days', 'note'];

    protected $attributes = ['kind' => 'doan'];

    protected $casts = [
        'days' => 'integer',
    ];

    /** Đổi danh sách → tính lại ngày ẩn của mọi đợt khởi hành tour nước ngoài. */
    protected static function booted(): void
    {
        static::saved(fn () => HanNopVisa::tinhLaiTatCa());
        static::deleted(fn () => HanNopVisa::tinhLaiTatCa());
    }

    public static function tenDang(?string $dang): ?string
    {
        return $dang ? (self::DANG[$dang] ?? $dang) : null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['country', 'kind', 'days', 'note'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('visa_deadline');
    }
}
