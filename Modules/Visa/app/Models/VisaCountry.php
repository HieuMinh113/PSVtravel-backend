<?php

namespace Modules\Visa\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class VisaCountry extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'flag_image', 'visa_type', 'price',
        'processing_time', 'success_rate', 'required_documents',
        'description', 'status', 'sort_order',
    ];

    protected $casts = [
        'price' => 'integer',
        'success_rate' => 'integer',
        'required_documents' => 'array',
        'sort_order' => 'integer',
    ];

    public function scopeDangHienThi($query)
    {
        return $query->where('status', 'published')->orderBy('sort_order');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug', 'visa_type', 'price', 'status', 'sort_order'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('visa_country');
    }
}