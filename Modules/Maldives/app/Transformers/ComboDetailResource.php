<?php

namespace Modules\Maldives\Transformers;

use App\Services\YouTube;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Maldives\Models\MaldivesComboDate;

/** Trang /maldives/{slug}: combo + resort + hạng phòng + ngày & giá + lịch trình */
class ComboDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = $this->resort;

        return [
            ...(new ComboListResource($this->resource))->toArray($request),
            'seo_title' => $this->seo_title,
            'highlights' => AnhMaldives::dong($this->highlights),
            'noi_khoi_hanh' => array_values(array_map(fn ($n) => [
                'city' => trim((string) ($n['city'] ?? '')),
                'surcharge_usd' => is_numeric($n['surcharge_usd'] ?? null) ? (int) $n['surcharge_usd'] : null,
                'flights' => AnhMaldives::dong($n['flights'] ?? ''),
            ], array_filter($this->departure_cities ?? [], fn ($n) => filled($n['city'] ?? null)))),
            'child_percent' => $this->child_percent,
            'child_age' => $this->child_age,
            'infant_note' => $this->infant_note,
            'min_group' => $this->min_group,
            'included' => AnhMaldives::dong($this->included),
            'excluded' => AnhMaldives::dong($this->excluded),
            'notes' => array_values(array_filter(array_map(fn ($n) => ['title' => trim((string) ($n['title'] ?? '')), 'content' => trim((string) ($n['content'] ?? ''))],
                $this->notes ?? []), fn ($n) => $n['title'] !== '' && $n['content'] !== '')),
            // Ngày đã qua không hiện (khách không đặt được); ngày hết phòng vẫn hiện để khách biết
            'dates' => $this->dates->filter(fn (MaldivesComboDate $d) => ! $d->start_date->lt(today()) && $d->status !== 'closed')->values()
                ->map(fn (MaldivesComboDate $d) => [
                    'id' => $d->id,
                    'start_date' => $d->start_date->toDateString(),
                    'label' => $d->label,
                    'status' => $d->status,
                    'prices' => (object) collect($d->prices ?? [])->filter(fn ($g) => is_numeric($g) && (int) $g > 0)->map(fn ($g) => (int) $g)->all(),
                ])->all(),
            'room_types' => $r?->roomTypes->map(fn ($p) => [
                'id' => $p->id, 'name' => $p->name, 'capacity' => $p->capacity,
                'description' => $p->description, 'image' => AnhMaldives::url($p->image),
            ])->all() ?? [],
            'days' => $this->days->map(fn ($d) => [
                'day_number' => $d->day_number, 'title' => $d->title, 'meals' => $d->meals,
                'description' => $d->description, 'images' => AnhMaldives::ds($d->images),
            ])->all(),
            'resort' => $r ? [
                'slug' => $r->slug,
                'name' => $r->name,
                'stars' => $r->stars,
                'atoll' => $r->atoll,
                'package_name' => $r->package_name,
                'intro' => $r->intro,
                'highlights' => AnhMaldives::dong($r->highlights),
                'cover_image' => AnhMaldives::url($r->cover_image),
                'gallery' => AnhMaldives::ds($r->gallery),
                'video_id' => YouTube::id($r->video_url),
                'amenities' => array_values(array_filter(array_map(fn ($a) => ['title' => trim((string) ($a['title'] ?? '')), 'items' => AnhMaldives::dong($a['items'] ?? '')],
                    $r->amenities ?? []), fn ($a) => $a['title'] !== '' && $a['items'] !== [])),
                'policies' => array_values(array_filter(array_map(fn ($a) => ['title' => trim((string) ($a['title'] ?? '')), 'content' => trim((string) ($a['content'] ?? ''))],
                    $r->policies ?? []), fn ($a) => $a['title'] !== '' && $a['content'] !== '')),
            ] : null,
        ];
    }
}
