<?php

namespace App\Models;

use App\Models\Concerns\ChuanHoaNoiDung;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Destination extends Model
{
    use ChuanHoaNoiDung, SoftDeletes;

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

    /** Ô nội dung soạn bằng RichEditor: tự sửa link, thu nhỏ ảnh khi lưu (ChuanHoaNoiDung) */
    public function truongNoiDung(): array
    {
        return ['description'];
    }
}
