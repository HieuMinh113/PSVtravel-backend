<?php

namespace App\Console\Commands;

use App\Jobs\PingIndexNow;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Báo IndexNow (Bing, Yandex, Naver, Seznam…) MỌI trang trong sitemap.xml một
 * lượt. Bing là nguồn tìm kiếm của ChatGPT và Copilot — web chưa có trong Bing
 * thì hai trợ lý này khó trích dẫn PSV. Bình thường chỉ tour vừa lưu mới được
 * báo; lệnh này dùng sau khi mở web, đổi nhiều nội dung, hoặc mỗi tháng một lần.
 *
 *   docker compose -f docker-compose.prod.yml exec app php artisan psv:bao-indexnow
 *   docker compose -f docker-compose.prod.yml exec app php artisan psv:bao-indexnow --xem   (chỉ đếm, không gửi)
 *
 * Đọc sitemap qua mạng nội bộ Docker (http://frontend:3000) vì VPS chặn gọi vòng
 * ra tên miền của chính nó; chạy ngoài Docker thì dùng --sitemap=URL.
 */
class BaoIndexNow extends Command
{
    protected $signature = 'psv:bao-indexnow
        {--sitemap=http://frontend:3000/sitemap.xml : Địa chỉ đọc sitemap}
        {--xem : Chỉ đếm số trang, không gửi}';

    protected $description = 'Báo IndexNow (Bing…) mọi trang trong sitemap.xml của website';

    public function handle(): int
    {
        try {
            $xml = Http::timeout(30)->get($this->option('sitemap'))->throw()->body();
        } catch (\Throwable $e) {
            $this->error('Không đọc được sitemap: '.$e->getMessage());

            return self::FAILURE;
        }

        // Chỉ gửi trang thuộc đúng tên miền website (IndexNow từ chối tên miền khác)
        $host = parse_url((string) config('app.frontend_url'), PHP_URL_HOST);
        preg_match_all('#<loc>\s*([^<\s]+)\s*</loc>#', $xml, $m);
        $urls = array_values(array_unique(array_filter(
            array_map('html_entity_decode', $m[1]),
            fn ($u) => parse_url($u, PHP_URL_HOST) === $host,
        )));

        $this->info('Sitemap có '.count($urls).' trang thuộc '.$host.'.');
        if ($this->option('xem') || ! $urls) {
            return self::SUCCESS;
        }

        foreach (array_chunk($urls, 1000) as $i => $lo) {
            $ma = PingIndexNow::gui($lo);
            $this->line(sprintf('Lô %d (%d trang): %s', $i + 1, count($lo), match (true) {
                $ma === null => 'không gửi (IndexNow đang tắt hoặc lỗi mạng — xem log)',
                $ma === 200, $ma === 202 => "đã nhận ({$ma})",
                $ma === 403 => '403 — không đọc được tệp khoá IndexNow trên website',
                $ma === 422 => '422 — trang không thuộc tên miền của khoá',
                $ma === 429 => '429 — gửi quá nhiều, thử lại sau',
                default => "trả về {$ma}",
            }));
        }

        return self::SUCCESS;
    }
}
