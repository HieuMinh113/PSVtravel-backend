<?php

namespace Modules\Banner\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Banner extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'title', 'subtitle', 'image', 'image_mobile', 'link',
        'status', 'start_at', 'end_at', 'sort_order',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'sort_order' => 'integer',
    ];

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
            ->logOnly(['title', 'status', 'link', 'start_at', 'end_at', 'sort_order'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('banner');
    }
}