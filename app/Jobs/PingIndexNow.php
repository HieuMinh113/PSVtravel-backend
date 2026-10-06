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
    public function __construct(public array $urls)
    {
    }

    public function handle(): void
    {
        if (! config('indexnow.enabled')) {
            return;
        }

        $key = config('indexnow.key');
        $frontend = rtrim((string) config('app.frontend_url'), '/');
        $host = parse_url($frontend, PHP_URL_HOST);

        $urls = array_values(array_filter(array_unique($this->urls)));
        if (! $key || ! $host || empty($urls)) {
            return;
        }

        $keyLocation = config('indexnow.key_location') ?: "{$frontend}/{$key}.txt";

        try {
            $res = Http::timeout(10)->post('https://api.indexnow.org/indexnow', [
                'host' => $host,
                'key' => $key,
                'keyLocation' => $keyLocation,
                'urlList' => $urls,
            ]);
            if (! $res->successful()) {
                Log::info('IndexNow trả về '.$res->status(), ['urls' => $urls]);
            }
        } catch (\Throwable $e) {
            // Không sao — chỉ là thông báo lập chỉ mục, thất bại thì thôi.
            Log::info('IndexNow lỗi: '.$e->getMessage());
        }
    }
}
