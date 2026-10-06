<?php

namespace Modules\Alliance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** Một sheet chỗ trống của đối tác liên minh (điều hành nhập link). */
class AllianceSource extends Model
{
    use LogsActivity;

    protected $fillable = ['name', 'sheet_url', 'contact', 'note', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'last_checked_at' => 'datetime',
        'last_success_at' => 'datetime',
        'sheet_updated_on' => 'date',
        'warnings' => 'array',
        'departures_count' => 'integer',
    ];

    public function tours(): HasMany
    {
        return $this->hasMany(AllianceTour::class);
    }

    public function departures(): HasMany
    {
        return $this->hasMany(AllianceDeparture::class);
    }

    /** Lần đọc gần nhất có lỗi (link hỏng, sheet bị khoá...). */
    public function dangLoi(): bool
    {
        return $this->last_error !== null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'sheet_url', 'contact', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('alliance_source');
    }
}
