<?php

namespace Modules\Guide\Models;

use App\Models\TeamMember;
use App\Models\User;
use App\Services\DoiDuongDan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Tour\Models\Tour;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Guide extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'title', 'seo_title', 'slug', 'excerpt', 'content', 'cover_image', 'video_url',
        'author_id', 'team_member_id', 'category', 'tour_id', 'view_count', 'status', 'published_at', 'sort_order',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'view_count' => 'integer',
        'sort_order' => 'integer',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    /** Tác giả hiện trên bài (thành viên Đội ngũ, có trang /tac-gia/{slug}) */
    public function tacGia(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'team_member_id');
    }

    protected static function booted(): void
    {
        // Đổi đường dẫn bài đã đăng → link cũ tự chuyển sang link mới (301)
        static::updated(function (self $g) {
            if ($g->wasChanged('slug') && $g->getOriginal('status') === 'published') {
                DoiDuongDan::ghi('/cam-nang/'.$g->getOriginal('slug'), '/cam-nang/'.$g->slug);
            }
        });
    }

    /** Các điểm trên bản đồ cẩm nang mở ra bài này. */
    public function spots(): HasMany
    {
        return $this->hasMany(GuideSpot::class);
    }

    /** Thành phố trên bản đồ dùng bài này làm bài tổng quan. */
    public function mapCities(): HasMany
    {
        return $this->hasMany(GuideCity::class);
    }

    public function scopeDangHienThi($query)
    {
        return $query->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'slug', 'status', 'published_at', 'category'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('guide');
    }
}
