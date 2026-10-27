<?php

namespace Modules\Maldives\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Resort ở Maldives: giới thiệu, ảnh, hạng phòng, tiện ích, quy định — nhập một
 * lần, dùng chung cho mọi combo của resort đó (bay hãng khác, thêm đêm…).
 */
class MaldivesResort extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'slug', 'name', 'stars', 'atoll', 'package_name', 'intro', 'highlights',
        'cover_image', 'gallery', 'video_url', 'amenities', 'policies', 'status', 'sort_order',
    ];

    protected $casts = [
        'stars' => 'integer',
        'highlights' => 'array',
        'gallery' => 'array',
        'amenities' => 'array',
        'policies' => 'array',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $r) {
            if (blank($r->slug)) {
                $goc = Str::slug((string) $r->name) ?: 'resort';
                $slug = $goc;
                for ($i = 2; static::withTrashed()->where('slug', $slug)->whereKeyNot($r->getKey())->exists(); $i++) {
                    $slug = "{$goc}-{$i}";
                }
                $r->slug = $slug;
            }
        });
    }

    public function roomTypes(): HasMany
    {
        return $this->hasMany(MaldivesRoomType::class, 'resort_id')->orderBy('sort_order')->orderBy('id');
    }

    public function combos(): HasMany
    {
        return $this->hasMany(MaldivesCombo::class, 'resort_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'slug', 'status'])->logOnlyDirty()->dontLogEmptyChanges()->useLogName('maldives');
    }
}
