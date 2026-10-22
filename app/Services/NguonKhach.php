<?php

namespace App\Services;

/**
 * Khách đến từ kênh nào (TikTok, Facebook, Google, ChatGPT…) — ghi vào đơn đặt
 * tour và yêu cầu liên hệ / team building để biết clip, chiến dịch nào ra đơn.
 *
 * Website nhớ nguồn của lượt vào gần nhất có nguồn (30 ngày) rồi gửi kèm đơn:
 * mã chiến dịch (utm_*), trang giới thiệu (referrer), dấu quảng cáo
 * (gclid / fbclid). Kênh được phân loại LẠI ở đây — không tin nhãn máy khách tự
 * đặt, chỉ dùng dữ liệu thô đã cắt gọn.
 */
class NguonKhach
{
    public const KENH = [
        'tiktok' => 'TikTok',
        'facebook' => 'Facebook',
        'facebook_ads' => 'Quảng cáo Facebook',
        'instagram' => 'Instagram',
        'zalo' => 'Zalo',
        'youtube' => 'YouTube',
        'google' => 'Google (tìm kiếm)',
        'google_ads' => 'Quảng cáo Google',
        'bing' => 'Bing',
        'chatgpt' => 'ChatGPT',
        'perplexity' => 'Perplexity',
        'gemini' => 'Gemini',
        'copilot' => 'Copilot',
        'claude' => 'Claude',
        'email' => 'Email',
        'qr' => 'Mã QR',
        'web_khac' => 'Trang web khác',
        'truc_tiep' => 'Vào thẳng',
        'khac' => 'Khác',
    ];

    /** Trợ lý AI — lượt khách đến từ đây nghĩa là PSV đang được AI trích dẫn */
    public const AI = ['chatgpt', 'perplexity', 'gemini', 'copilot', 'claude'];

    private const TRUONG = ['source', 'medium', 'campaign', 'content', 'term', 'referrer', 'landing'];

    /**
     * Dữ liệu nguồn máy khách gửi lên → ['kenh' => …, 'utm' => [...] | null].
     * Không có gì (đơn cũ, trình duyệt chặn lưu trữ) thì kenh = null: "không rõ",
     * khác với "vào thẳng".
     *
     * @return array{kenh: ?string, utm: ?array}
     */
    public static function tuDuLieu(mixed $nguon): array
    {
        if (! is_array($nguon)) {
            return ['kenh' => null, 'utm' => null];
        }

        $utm = [];
        foreach (self::TRUONG as $k) {
            $v = $nguon[$k] ?? null;
            if (is_string($v) && ($v = trim(mb_substr(strip_tags($v), 0, 150))) !== '') {
                $utm[$k] = $v;
            }
        }
        // Chỉ giữ tên miền của trang giới thiệu, bỏ đường dẫn / tham số (có thể chứa dữ liệu cá nhân)
        if (isset($utm['referrer'])) {
            $host = parse_url(str_contains($utm['referrer'], '://') ? $utm['referrer'] : 'https://'.$utm['referrer'], PHP_URL_HOST);
            $host = is_string($host) ? strtolower(preg_replace('/^www\./', '', $host)) : null;
            if ($host) {
                $utm['referrer'] = $host;
            } else {
                unset($utm['referrer']);
            }
        }
        foreach (['gclid', 'fbclid', 'ttclid'] as $k) {
            if (! empty($nguon[$k])) {
                $utm[$k] = true;
            }
        }

        return ['kenh' => self::phanLoai($utm), 'utm' => $utm ?: null];
    }

    public static function phanLoai(array $u): string
    {
        $src = strtolower((string) ($u['source'] ?? ''));
        $med = strtolower((string) ($u['medium'] ?? ''));
        $ref = strtolower((string) ($u['referrer'] ?? ''));
        $quangCao = in_array($med, ['cpc', 'ppc', 'paid', 'paid_social', 'ads', 'ad'], true);

        if (! empty($u['gclid'])) {
            return 'google_ads';
        }
        if (! empty($u['fbclid']) && $quangCao) {
            return 'facebook_ads';
        }

        $theoTen = self::theoTen($src !== '' ? $src : $ref);
        if ($theoTen === 'google' && $quangCao) {
            return 'google_ads';
        }
        if ($theoTen === 'facebook' && $quangCao) {
            return 'facebook_ads';
        }
        if ($theoTen) {
            return $theoTen;
        }
        if ($src !== '') {
            return in_array($med, ['email', 'newsletter'], true) ? 'email' : 'khac';
        }
        if (! empty($u['fbclid'])) {
            return 'facebook';
        }

        return $ref !== '' ? 'web_khac' : 'truc_tiep';
    }

    /** Tên nguồn utm_source hoặc tên miền giới thiệu → kênh đã biết */
    private static function theoTen(string $s): ?string
    {
        if ($s === '') {
            return null;
        }
        $mau = [
            'chatgpt' => '/chatgpt|chat\.openai|openai/',
            'perplexity' => '/perplexity/',
            'gemini' => '/gemini|bard\.google/',
            'copilot' => '/copilot/',
            'claude' => '/claude/',
            'tiktok' => '/tiktok/',
            'instagram' => '/instagram|^ig$/',
            'facebook' => '/facebook|^fb$|fb\.com|messenger|^m\.me$/',
            'zalo' => '/zalo/',
            'youtube' => '/youtube|youtu\.be/',
            'google' => '/google/',
            'bing' => '/bing/',
            'email' => '/mail|newsletter/',
            'qr' => '/^qr$|qr[-_]/',
        ];
        foreach ($mau as $kenh => $re) {
            if (preg_match($re, $s)) {
                return $kenh;
            }
        }

        return null;
    }

    public static function nhan(?string $kenh): string
    {
        return $kenh === null ? 'Không rõ' : (self::KENH[$kenh] ?? $kenh);
    }

    public static function laAI(?string $kenh): bool
    {
        return in_array($kenh, self::AI, true);
    }
}
