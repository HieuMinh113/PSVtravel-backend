<?php

namespace Modules\Moment\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MomentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $videoId = \App\Services\YouTube::id($this->video_url);

        return [
            // Không có ảnh riêng mà có video thì lấy ảnh thu nhỏ của video làm ảnh bìa
            'image' => $this->urlAnh($this->image)
                ?? ($videoId ? "https://i.ytimg.com/vi/{$videoId}/hqdefault.jpg" : null),
            // Chỉ trả MÃ video — website tự dựng link nhúng youtube-nocookie
            'video_id' => $videoId,
            'gallery' => collect($this->gallery ?? [])
                ->map(fn ($path) => $this->urlAnh($path))
                ->filter()
                ->values()
                ->all(),
            'caption' => $this->caption,
            'customer_name' => $this->customer_name,
            'tour_name' => $this->whenLoaded('tour', fn () => $this->tour?->name),
        ];
    }

    // Đường dẫn tuyệt đối cho ảnh (giữ nguyên nếu đã là URL http).
    private function urlAnh(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return str_starts_with($path, 'http') ? $path : asset('storage/'.$path);
    }
}
