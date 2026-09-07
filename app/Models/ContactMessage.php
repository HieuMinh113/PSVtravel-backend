<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContactMessage extends Model
{
    use SoftDeletes;

    public const TRANG_THAI = [
        'new' => 'Mới',
        'handling' => 'Đang xử lý',
        'done' => 'Đã xong',
    ];

    // Nguồn tin — dùng để lọc và gắn nhãn trong hộp thư
    public const NGUON = [
        'contact' => 'Liên hệ',
        'team_building' => 'Team Building',
    ];

    protected $fillable = [
        'name', 'phone', 'email', 'subject', 'source', 'message', 'meta',
        'status', 'admin_note', 'handled_by', 'handled_at', 'ip',
    ];

    protected $casts = [
        'handled_at' => 'datetime',
        'meta' => 'array',
    ];

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
