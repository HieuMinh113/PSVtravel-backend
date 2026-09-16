<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobPosting extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title', 'slug', 'department', 'location', 'employment_type', 'salary_range',
        'quantity', 'description', 'requirements', 'benefits', 'deadline',
        'status', 'sort_order',
    ];

    protected $casts = [
        'deadline' => 'date',
        'quantity' => 'integer',
        'sort_order' => 'integer',
    ];

    public function scopeDangHienThi($query)
    {
        return $query->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('deadline')->orWhere('deadline', '>=', now()->startOfDay()))
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
