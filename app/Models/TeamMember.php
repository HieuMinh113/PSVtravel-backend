<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Modules\Guide\Models\Guide;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

// Thành viên đội ngũ / ban lãnh đạo. Đặt ở app\Models (giống Event) — tính năng
// gọn, không cần module riêng.
class TeamMember extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'user_id', 'position', 'email', 'photo', 'bio', 'status', 'sort_order',
        'years_experience', 'expertise', 'countries', 'profile', 'social_links',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'years_experience' => 'integer',
        'countries' => 'array',
        'social_links' => 'array',
    ];

    protected static function booted(): void
    {
        // Chưa đặt đường dẫn trang tác giả thì lấy theo tên, trùng thì thêm số
        static::saving(function (TeamMember $m) {
            if (blank($m->slug)) {
                $goc = Str::slug((string) $m->name) ?: 'tac-gia';
                $slug = $goc;
                for ($i = 2; static::withTrashed()->where('slug', $slug)->whereKeyNot($m->getKey())->exists(); $i++) {
                    $slug = "{$goc}-{$i}";
                }
                $m->slug = $slug;
            }
        });
    }

    public function scopeDangHienThi($query)
    {
        return $query->where('status', 'published');
    }

    /** Có trang tác giả công khai: đang hiển thị và có ít nhất một bài cẩm nang đã đăng */
    public function scopeCoTrangTacGia(Builder $query): Builder
    {
        return $query->dangHienThi()->whereNotNull('slug')
            ->whereHas('baiViet', fn ($q) => $q->dangHienThi());
    }

    /** Bài cẩm nang người này đứng tên tác giả */
    public function baiViet(): HasMany
    {
        return $this->hasMany(Guide::class, 'team_member_id');
    }

    /** Tài khoản admin của người này (bài họ viết tự chọn họ làm tác giả) */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug', 'position', 'status', 'sort_order', 'years_experience', 'expertise'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('team_member');
    }
}
