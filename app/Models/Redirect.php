<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Chuyển hướng link cũ (xem App\Services\LinkCu) */
class Redirect extends Model
{
    public const MA = [
        301 => 'Chuyển sang trang mới (301)',
        410 => 'Trang đã gỡ hẳn (410)',
        404 => 'Chưa khớp — cần xử lý',
    ];

    public const NGUON = ['tu_dong' => 'Tự động', 'tay' => 'Nhân viên'];

    protected $fillable = ['old_path', 'new_path', 'status_code', 'source', 'note'];

    protected $casts = [
        'status_code' => 'integer',
        'hits' => 'integer',
        'last_hit_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Đổi sang 410 / chưa khớp thì bỏ trang đích cũ (ô "Chuyển tới" bị ẩn
        // trong form nên không tự xoá)
        static::saving(function (Redirect $r) {
            if ((int) $r->status_code !== 301) {
                $r->new_path = null;
            }
        });
    }
}
