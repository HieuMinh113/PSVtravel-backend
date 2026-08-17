<?php

namespace Modules\Banner\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BannerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'image' => $this->url($this->image),
            'image_mobile' => $this->image_mobile ? $this->url($this->image_mobile) : $this->url($this->image),
            'link' => $this->link,
        ];
    }

    private function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return str_starts_with($path, 'http') ? $path : asset('storage/'.$path);
    }
}