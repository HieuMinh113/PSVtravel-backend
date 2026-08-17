<?php

namespace Modules\Moment\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MomentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'image' => $this->image
                ? (str_starts_with($this->image, 'http') ? $this->image : asset('storage/'.$this->image))
                : null,
            'caption' => $this->caption,
            'customer_name' => $this->customer_name,
            'tour_name' => $this->whenLoaded('tour', fn () => $this->tour?->name),
        ];
    }
}