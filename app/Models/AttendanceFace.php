<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Khuôn mặt nhân viên đăng ký để chấm công: 128 số đặc trưng (trình duyệt tính
 * bằng face-api, KHÔNG gửi ảnh ra dịch vụ ngoài) + ảnh lúc đăng ký. Đăng ký lại
 * phải nhờ quản lý xoá bản cũ.
 */
class AttendanceFace extends Model
{
    protected $fillable = ['user_id', 'descriptor', 'photo', 'consented_at'];

    protected $hidden = ['descriptor'];

    protected $casts = [
        'descriptor' => 'array',
        'consented_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleted(fn (AttendanceFace $f) => $f->photo && Storage::disk(Attendance::DIA)->delete($f->photo));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
