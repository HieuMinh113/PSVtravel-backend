<?php

namespace Modules\Category\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'type' => $this->type,
            'image' => $this->image
                ? (str_starts_with($this->image, 'http') ? $this->image : asset('storage/'.$this->image))
                : null,
            'description' => $this->description,
            'tours_count' => $this->whenCounted('tours'),
        ];
    }
}