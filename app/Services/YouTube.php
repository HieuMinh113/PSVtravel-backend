<?php

namespace App\Services;

/**
 * Xử lý link YouTube dùng chung cho các ô "Link video" trong trang quản trị
 * (tour, video trang chủ, khoảnh khắc du khách).
 *
 * Chấp nhận mọi kiểu link nhân viên hay dán:
 *   https://www.youtube.com/watch?v=ID
 *   https://youtu.be/ID
 *   https://www.youtube.com/shorts/ID
 *   https://www.youtube.com/embed/ID
 *   https://m.youtube.com/watch?v=ID&t=30s
 */
class YouTube
{
    // Mã video YouTube luôn là 11 ký tự chữ, số, gạch ngang, gạch dưới.
    public const MAU = '~^(?:https?://)?(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|shorts/|embed/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~';

    // Lấy mã video từ link; không phải link YouTube hợp lệ thì trả null.
    public static function id(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        return preg_match(self::MAU, trim($url), $m) ? $m[1] : null;
    }

    // Quy tắc kiểm tra dùng cho ô nhập trong Filament (xem App\Rules\LinkYouTube).
    public static function quyTac(): \App\Rules\LinkYouTube
    {
        return new \App\Rules\LinkYouTube;
    }
}
