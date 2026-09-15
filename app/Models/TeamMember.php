<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

// Thành viên đội ngũ / ban lãnh đạo. Đặt ở app\Models (giống Event) — tính năng
// gọn, không cần module riêng.
class TeamMember extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'name', 'position', 'email', 'photo', 'bio', 'status', 'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function scopeDangHienThi($query)
    {
        return $query->where('status', 'published');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'position', 'status', 'sort_order'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('team_member');
    }
}
