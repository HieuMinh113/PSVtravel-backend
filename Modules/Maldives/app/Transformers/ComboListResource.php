<?php

namespace Modules\Maldives\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Maldives\Models\MaldivesComboDate;

/** Thẻ combo ở trang /maldives */
class ComboListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $resort = $this->resort;
        $sapToi = $this->dates->filter(fn (MaldivesComboDate $d) => $d->status === 'open' && ! $d->start_date->lt(today()))->values();

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'summary' => $this->summary,
            'cover_image' => AnhMaldives::url($this->cover_image ?: ($resort?->cover_image ?: ($resort?->gallery[0] ?? null))),
            'duration_days' => $this->duration_days,
            'duration_nights' => $this->duration_nights,
            'stay_summary' => $this->stay_summary,
            'airline' => $this->airline,
            'transfer' => $this->transfer,
            'gia_tu_usd' => $this->giaTu(),
            'ngay_khoi_hanh' => $sapToi->take(6)->map(fn ($d) => ['start_date' => $d->start_date->toDateString(), 'label' => $d->label])->all(),
            'noi_khoi_hanh' => array_values(array_filter(array_map(fn ($n) => $n['city'] ?? null, $this->departure_cities ?? []))),
            'is_featured' => $this->is_featured,
            'updated_at' => $this->updated_at?->toIso8601String(),
            'resort' => $resort ? ['slug' => $resort->slug, 'name' => $resort->name, 'stars' => $resort->stars, 'atoll' => $resort->atoll] : null,
        ];
    }
}
