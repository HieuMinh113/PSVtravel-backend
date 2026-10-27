<?php

namespace Modules\Maldives\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Hạng phòng của resort (Water Villa, Lagoon Villa 2 ngủ…) — giá đặt theo từng ngày khởi hành của combo */
class MaldivesRoomType extends Model
{
    protected $fillable = ['resort_id', 'name', 'capacity', 'description', 'image', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function resort(): BelongsTo
    {
        return $this->belongsTo(MaldivesResort::class, 'resort_id');
    }
}
