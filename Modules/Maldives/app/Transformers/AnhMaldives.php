<?php

namespace Modules\Maldives\Transformers;

/** Đường dẫn ảnh đầy đủ cho website */
class AnhMaldives
{
    public static function url(?string $p): ?string
    {
        if (blank($p)) {
            return null;
        }

        return str_starts_with($p, 'http') ? $p : asset('storage/'.$p);
    }

    /** @return list<string> */
    public static function ds(?array $ds): array
    {
        return array_values(array_filter(array_map(fn ($p) => is_string($p) ? self::url($p) : null, $ds ?? [])));
    }

    /** Mỗi dòng một mục, bỏ dòng trống */
    public static function dong(mixed $v): array
    {
        $ds = is_array($v) ? $v : preg_split('/\r\n|\r|\n/', (string) $v);

        return array_values(array_filter(array_map(fn ($d) => trim((string) $d), $ds), fn ($d) => $d !== ''));
    }
}
