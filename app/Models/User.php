<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Activitylog\Models\Concerns\HasActivity;
use Spatie\Activitylog\Support\LogOptions;
class User extends Authenticatable implements FilamentUser
{
    use HasActivity, HasApiTokens, HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'avatar',
        'google_id',
        'locale',
        'loyalty_points',
        'password',
    ];

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
            'password' => 'hashed',
        ];
    }

    // Chỉ nội bộ mới vào được trang quản trị; khách hàng bị chặn
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->vaoDuocQuanTri();
    }

    /**
     * Được vào trang quản trị hay không — CĂN CỨ VÀO QUYỀN, không phải tên vai trò.
     *
     * Trước đây chỗ này viết cứng ba tên: 'super_admin', 'admin', 'staff'.
     * Mà tên vai trò là dữ liệu nhân viên được phép sửa ngay trong trang quản
     * trị. Đổi tên "staff" thành "STAFF SALE", hay tạo vai trò mới "Supper
     * Admin 2", là người mang vai trò đó lập tức bị coi như khách hàng thường:
     * không vào được /admin, menu ngoài web cũng mất luôn lối tắt — mà không
     * có một dòng báo lỗi nào. Đã xảy ra thật với chính tài khoản chủ hệ thống.
     *
     * Cách này tự đúng về sau: đặt tên vai trò gì tuỳ ý, chỉ cần tick quyền
     * cho nó là vào được. Chưa tick quyền nào thì vào cũng chẳng làm được gì —
     * chặn luôn từ cửa còn rõ ràng hơn là cho vào một trang trống.
     */
    public function vaoDuocQuanTri(): bool
    {
        // Vai trò siêu quản trị luôn vào được. Nếu không có ngoại lệ này, chỉ
        // cần lỡ tay bỏ tick hết quyền của nó là KHÔNG CÒN AI vào được để sửa
        // lại — tự khoá mình ngoài cửa. Tên lấy từ cấu hình Shield, không viết cứng.
        $sieuQuanTri = config('filament-shield.super_admin.name');
        if ($sieuQuanTri && $this->hasRole($sieuQuanTri)) {
            return true;
        }

        // Dùng exists() thay vì tải cả danh sách quyền: hàm này chạy ở mọi lần
        // tải trang quản trị và mọi lần website hỏi thông tin người đăng nhập.
        return $this->roles()->whereHas('permissions')->exists()
            || $this->permissions()->exists();
    }
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'phone'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('user');
    }
}