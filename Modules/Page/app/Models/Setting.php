<?php

namespace Modules\Page\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Setting extends Model
{
    use LogsActivity;

    protected $fillable = ['key', 'value', 'group', 'label', 'type', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    // Xoá bộ nhớ đệm mỗi khi có thay đổi
    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('psv_settings'));
        static::deleted(fn () => Cache::forget('psv_settings'));
    }

    // Lấy nhanh một cấu hình: Setting::lay('hotline')
    public static function lay(string $key, ?string $macDinh = null): ?string
    {
        $tatCa = Cache::rememberForever('psv_settings', fn () => static::pluck('value', 'key')->toArray());

        return $tatCa[$key] ?? $macDinh;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['key', 'value'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('setting');
    }
}