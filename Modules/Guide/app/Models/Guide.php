<?php

namespace Modules\Guide\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Guide extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'title', 'slug', 'excerpt', 'content', 'cover_image',
        'author_id', 'category', 'view_count', 'status', 'published_at', 'sort_order',
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