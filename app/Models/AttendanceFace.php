<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Khuôn mặt nhân viên đăng ký để chấm công: 128 số đặc trưng (trình duyệt tính
 * bằng face-api, KHÔNG gửi ảnh ra dịch vụ ngoài) + ảnh lúc đăng ký. Đăng ký lại
 * phải nhờ quản lý xoá bản cũ.
 *
 * Mẫu thêm theo loại máy (extra_descriptors): đăng ký bằng điện thoại mà chấm
 * bằng webcam máy tính thì độ lệch hay sát ngưỡng → lần đầu webcam KHỚP (so với
 * mẫu đăng ký) thì lưu thêm mẫu đó; lần sau so với mẫu gần nhất.
 */
class AttendanceFace extends Model
{
    protected $fillable = ['user_id', 'descriptor', 'photo', 'consented_at', 'device'];

    protected $hidden = ['descriptor', 'extra_descriptors'];

    protected $casts = [
        'descriptor' => 'array',
        'extra_descriptors' => 'array',
        'consented_at' => 'datetime',
    ];

    /** Mẫu đăng ký + các mẫu thêm theo loại máy. @return list<array> */
    public function cacMau(): array
    {
        $ds = [$this->descriptor];
        foreach ((array) $this->extra_descriptors as $m) {
            if (is_array($m['descriptor'] ?? null)) {
                $ds[] = $m['descriptor'];
            }
        }

        return $ds;
    }

    protected static function booted(): void
    {
        static::deleted(fn (AttendanceFace $f) => $f->photo && Storage::disk(Attendance::DIA)->delete($f->photo));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
