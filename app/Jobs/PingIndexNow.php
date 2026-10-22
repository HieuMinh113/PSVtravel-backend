<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Báo IndexNow rằng vài URL vừa thay đổi.
 *
 * Chạy trong hàng đợi để KHÔNG làm chậm thao tác lưu trong admin, và lỗi mạng
 * (IndexNow chết, hết giờ) cũng không ảnh hưởng gì tới việc lưu tour.
 */
class PingIndexNow implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @param array<int,string> $urls */
    public function __construct(public array $urls) {}

    public function handle(): void
    {
        $ma = self::gui($this->urls);
        if ($ma !== null && ($ma < 200 || $ma >= 300)) {
            Log::info('IndexNow trả về '.$ma, ['urls' => $this->urls]);
        }
    }

    /**
     * Gửi một lô URL (tối đa 10.000) cho IndexNow. Trả mã HTTP, null nếu tắt /
     * thiếu cấu hình / lỗi mạng — chỉ là thông báo lập chỉ mục, thất bại thì thôi.
     *
     * @param  array<int,string>  $urls
     */
    public static function gui(array $urls): ?int
    {
        if (! config('indexnow.enabled')) {
            return null;
        }

        $key = config('indexnow.key');
        $frontend = rtrim((string) config('app.frontend_url'), '/');
        $host = parse_url($frontend, PHP_URL_HOST);

        $urls = array_values(array_filter(array_unique($urls)));
        if (! $key || ! $host || empty($urls)) {
            return null;
        }

        $keyLocation = config('indexnow.key_location') ?: "{$frontend}/{$key}.txt";

        try {
            return Http::timeout(20)->post('https://api.indexnow.org/indexnow', [
                'host' => $host,
                'key' => $key,
                'keyLocation' => $keyLocation,
                'urlList' => $urls,
            ])->status();
        } catch (\Throwable $e) {
            Log::info('IndexNow lỗi: '.$e->getMessage());

            return null;
        }
    }
}
