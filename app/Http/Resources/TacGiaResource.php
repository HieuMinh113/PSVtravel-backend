<?php

namespace App\Http\Resources;

use App\Models\TeamMember;

/**
 * Thông tin tác giả bài cẩm nang cho website (trang /tac-gia/{slug}, khung
 * "Về tác giả" cuối bài, dữ liệu Person gửi Google). KHÔNG trả email hay tài
 * khoản admin của người đó.
 */
class TacGiaResource
{
    /** Tóm tắt cho bài viết; null nếu người này không có trang công khai */
    public static function tomTat(?TeamMember $m): ?array
    {
        if (! $m || $m->status !== 'published' || blank($m->slug) || $m->trashed()) {
            return null;
        }

        return [
            'slug' => $m->slug,
            'name' => $m->name,
            'position' => $m->position,
            'photo' => self::anh($m->photo),
            'years_experience' => $m->years_experience ?: null,
            'expertise' => $m->expertise,
            'bio' => $m->bio,
        ];
    }

    /** Đầy đủ cho trang tác giả */
    public static function chiTiet(TeamMember $m): array
    {
        return [
            ...self::tomTat($m),
            'countries' => array_values(array_filter(array_map('trim', (array) $m->countries))),
            'profile' => $m->profile,
            // Chỉ link http(s) — dùng làm "sameAs" cho Google (trang mạng xã hội của người này)
            'social_links' => array_values(array_filter((array) $m->social_links,
                fn ($u) => is_string($u) && preg_match('#^https?://#i', $u))),
            'updated_at' => $m->updated_at?->toIso8601String(),
        ];
    }

    private static function anh(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return str_starts_with($path, 'http') ? $path : asset('storage/'.$path);
    }
}
