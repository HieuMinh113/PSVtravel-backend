<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Destination extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'image', 'summary', 'description', 'region',
        'category_slug', 'is_featured', 'status', 'sort_order',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeDangHienThi($query)
    {
        return $query->where('status', 'published')->orderBy('sort_order')->orderBy('id');
    }
}
