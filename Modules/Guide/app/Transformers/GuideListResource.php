<?php

namespace Modules\Guide\Transformers;

use App\Http\Resources\TacGiaResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuideListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'cover_image' => $this->anh($this->cover_image),
            'category' => $this->category,
            'author_name' => TacGiaResource::tomTat($this->relationLoaded('tacGia') ? $this->tacGia : null)['name']
                ?? ($this->relationLoaded('author') ? $this->author?->name : null),
            'view_count' => $this->view_count,
            'published_at' => $this->published_at?->format('Y-m-d'),
        ];
    }

    private function anh(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return str_starts_with($path, 'http') ? $path : asset('storage/'.$path);
    }
}
