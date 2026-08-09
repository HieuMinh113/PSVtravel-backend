<?php

namespace Modules\Visa\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class VisaProvider extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'name', 'contact_person', 'phone', 'email', 'address', 'note', 'status',
    ];

    public function scopeDangHoatDong($query)
    {
        return $query->where('status', 'active');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'contact_person', 'phone', 'email', 'status'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('visa_provider');
    }
}