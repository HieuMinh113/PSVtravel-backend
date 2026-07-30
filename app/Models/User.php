<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected $fillable = [
        'role_id',
        'name',
        'email',
        'phone',
        'avatar',
        'google_id',
        'locale',
        'password',
    ];

    // Các trường không bao giờ được trả về trong API response
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed', // Laravel tự băm mật khẩu, không bao giờ lưu dạng chữ thường
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    // Kiểm tra người dùng có thuộc 1 trong các vai trò được truyền vào hay không
    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role?->name, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::ADMIN);
    }

    public function isStaff(): bool
    {
        return $this->hasRole(Role::STAFF);
    }
}