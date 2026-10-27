<?php

namespace Modules\Maldives\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một ngày trong lịch trình combo */
class MaldivesComboDay extends Model
{
    protected $fillable = ['combo_id', 'day_number', 'title', 'meals', 'description', 'images'];

    protected $casts = ['day_number' => 'integer', 'images' => 'array'];

    public function combo(): BelongsTo
    {
        return $this->belongsTo(MaldivesCombo::class, 'combo_id');
    }
}
