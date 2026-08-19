<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    protected $fillable = [
        'email', 'purpose', 'code_hash', 'attempts',
        'ip_address', 'expires_at', 'consumed_at',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    // Mã còn dùng được: chưa bị dùng và chưa hết hạn
    public function scopeConHieuLuc($query)
    {
        return $query->whereNull('consumed_at')->where('expires_at', '>', now());
    }
}
