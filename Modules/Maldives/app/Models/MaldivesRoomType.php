<?php

namespace Modules\Maldives\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Hạng phòng của resort (Water Villa, Lagoon Villa 2 ngủ…) — giá đặt theo từng ngày khởi hành của combo */
class MaldivesRoomType extends Model
{
    protected $fillable = ['resort_id', 'name', 'capacity', 'adults_only', 'description', 'image', 'sort_order'];

    /** adults_only: chỉ nhận người lớn (từ 18 tuổi) — không nhận trẻ em, em bé */
    protected $casts = ['sort_order' => 'integer', 'adults_only' => 'boolean'];

    public function resort(): BelongsTo
    {
        return $this->belongsTo(MaldivesResort::class, 'resort_id');
    }
}
