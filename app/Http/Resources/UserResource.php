<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->avatar
                ? (str_starts_with($this->avatar, 'http') ? $this->avatar : asset('storage/'.$this->avatar))
                : null,
            'loyalty_points' => $this->loyalty_points,
            'verified' => (bool) $this->email_verified_at,
            'created_at' => $this->created_at?->format('d/m/Y'),

            // Nhân viên và quản trị viên đăng nhập ở website thì menu tài khoản
            // hiện thêm lối tắt sang trang quản trị — họ hay xem web như khách
            // rồi cần nhảy sang admin xử lý đơn.
            //
            // Dùng đúng danh sách vai trò của canAccessPanel() trong User model,
            // để hai nơi không bao giờ lệch nhau.
            'la_nhan_vien' => $this->hasAnyRole(['super_admin', 'admin', 'staff']),
            // Trang quản trị nằm khác cổng với website nên phải trả về đường dẫn
            // đầy đủ, frontend không tự đoán được.
            'admin_url' => $this->hasAnyRole(['super_admin', 'admin', 'staff'])
                ? rtrim((string) config('app.url'), '/').'/admin'
                : null,
        ];
    }
}
