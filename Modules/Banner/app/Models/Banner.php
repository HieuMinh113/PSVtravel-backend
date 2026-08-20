<?php

namespace Modules\Banner\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Banner extends Model
{
    use LogsActivity, SoftDeletes;

    // Các vị trí banner có thể đặt trên web. Thêm vị trí mới thì khai báo ở đây,
    // form trong admin và API tự nhận theo danh sách này.
    public const VI_TRI = [
        'promo' => 'Banner khuyến mãi (trang chủ)',
        'orbit_home' => 'Ảnh vòng xoay — Trang chủ',
        'orbit_domestic' => 'Ảnh vòng xoay — Tour trong nước',
        'orbit_abroad' => 'Ảnh vòng xoay — Tour nước ngoài',
    ];

    protected $fillable = [
        'position', 'title', 'subtitle', 'image', 'image_mobile', 'link',
        'status', 'start_at', 'end_at', 'sort_order',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    // Lọc theo vị trí đặt banner
    public function scopeViTri($query, string $viTri)
    {
        return $query->where('position', $viTri);
    }

    // Banner đang thực sự hiển thị: đã bật + trong khoảng thời gian cho phép
    public function scopeDangHienThi($query)
    {
        return $query->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('start_at')->orWhere('start_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('end_at')->orWhere('end_at', '>=', now()))
            ->orderBy('sort_order');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['position', 'title', 'status', 'link', 'start_at', 'end_at', 'sort_order'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('banner');
    }
}