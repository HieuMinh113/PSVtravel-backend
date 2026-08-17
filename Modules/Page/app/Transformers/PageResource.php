<?php

namespace Modules\Page\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'body' => $this->body,
            'hero_image' => $this->hero_image
                ? (str_starts_with($this->hero_image, 'http') ? $this->hero_image : asset('storage/'.$this->hero_image))
                : null,
            'meta_title' => $this->meta_title ?: $this->title,
            'meta_description' => $this->meta_description,
            'updated_at' => $this->updated_at,
        ];
    }
}