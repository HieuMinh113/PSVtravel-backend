<?php

namespace Modules\Page\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Page extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'slug', 'title', 'meta_title', 'meta_description',
        'hero_image', 'body', 'content', 'is_system', 'status',
    ];

    protected $casts = [
        'content' => 'array',
        'is_system' => 'boolean',
    ];

    public function scopeDangHienThi($query)
    {
        return $query->where('status', 'published');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['slug', 'title', 'status', 'meta_title'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('page');
    }
}