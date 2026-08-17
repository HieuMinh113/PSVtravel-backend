<?php

namespace Modules\Guide\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuideDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'cover_image' => $this->anh($this->cover_image),
            'category' => $this->category,
            'author_name' => $this->whenLoaded('author', fn () => $this->author?->name),
            'view_count' => $this->view_count,
            'published_at' => $this->published_at?->format('Y-m-d'),
            'meta_title' => $this->title,
            'meta_description' => $this->excerpt,
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