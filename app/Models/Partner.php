<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Partner extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'logo', 'link_url', 'status', 'sort_order',
    ];

    protected $casts = ['sort_order' => 'integer'];

    public function scopeDangHienThi($query)
    {
        return $query->where('status', 'published')->orderBy('sort_order')->orderBy('id');
    }
}
