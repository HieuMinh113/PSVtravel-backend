<?php

namespace Modules\Guide\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Điểm trên bản đồ thành phố. Bấm vào trên website thì mở bài cẩm nang gắn kèm. */
class GuideSpot extends Model
{
    /** Loại điểm — website hiện biểu tượng + màu riêng cho từng loại. */
    public const LOAI = [
        'tham_quan' => 'Tham quan',
        'an_uong' => 'Ăn uống',
        'mua_sam' => 'Mua sắm',
        'thien_nhien' => 'Thiên nhiên',
        'luu_tru' => 'Lưu trú',
        'di_chuyen' => 'Di chuyển',
        'khac' => 'Khác',
    ];

    protected $fillable = [
        'guide_city_id', 'name', 'type', 'lat', 'lng', 'summary', 'image', 'guide_id', 'status', 'sort_order',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'sort_order' => 'integer',
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(GuideCity::class, 'guide_city_id');
    }

    public function guide(): BelongsTo
    {
        return $this->belongsTo(Guide::class);
    }

    public function scopeDangHienThi($query)
    {
        return $query->where('status', 'published')->orderBy('sort_order')->orderBy('name');
    }
}
