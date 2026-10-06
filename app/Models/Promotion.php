<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promotion extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title', 'description', 'image', 'discount_label', 'price', 'old_price',
        'link_url', 'badge', 'status', 'sort_order', 'ends_at',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'price' => 'integer',
        'old_price' => 'integer',
        'ends_at' => 'date',
    ];

    public function scopeDangHienThi($query)
    {
        return $query->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()->startOfDay()))
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
