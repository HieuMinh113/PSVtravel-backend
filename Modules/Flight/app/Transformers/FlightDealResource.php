<?php

namespace Modules\Flight\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FlightDealResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'airline' => $this->whenLoaded('airline', fn () => [
                'code' => $this->airline?->code,
                'name' => $this->airline?->name,
                'logo' => $this->airline?->logo
                    ? (str_starts_with($this->airline->logo, 'http') ? $this->airline->logo : asset('storage/'.$this->airline->logo))
                    : null,
            ]),
            'from_city' => $this->from_city,
            'to_city' => $this->to_city,
            'trip_type' => $this->trip_type,
            'price' => $this->price,
            'old_price' => $this->old_price,
            'valid_to' => $this->valid_to?->format('Y-m-d'),
            'note' => $this->note,
        ];
    }
}