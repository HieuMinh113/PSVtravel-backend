<?php

namespace App\Console\Commands;

use App\Models\Destination;
use App\Models\Event;
use App\Models\JobPosting;
use App\Services\SuaLinkNoiDung;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Modules\Guide\Models\Guide;
use Modules\Page\Models\Page;
use Modules\Visa\Models\VisaCountry;

/**
 * Sửa link trong nội dung đã soạn từ trước (bài lưu mới tự sửa khi lưu): link
 * www / psvtravel.com.vn / có "/" cuối / trỏ link web cũ đã chuyển hướng → trỏ
 * thẳng trang đích. Liệt kê thêm link tới trang đã gỡ để nhân viên sửa tay.
 *
 *   docker compose -f docker-compose.prod.yml exec -T app php artisan psv:sua-link-noi-dung --xem
 *   docker compose -f docker-compose.prod.yml exec -T app php artisan psv:sua-link-noi-dung
 */
class SuaLinkNoiDungCu extends Command
{
    protected $signature = 'psv:sua-link-noi-dung {--xem : Chỉ liệt kê, không lưu}';

    protected $description = 'Sửa link nội bộ trong nội dung bài (bỏ www, dấu / cuối, link đã chuyển hướng)';

    /** @var array<class-string<Model>, string> */
    private const LOAI = [
        Guide::class => 'Cẩm nang',
        Destination::class => 'Điểm đến',
        VisaCountry::class => 'Visa',
        Event::class => 'Team building',
        Page::class => 'Trang',
        JobPosting::class => 'Tuyển dụng',
    ];

    public function handle(): int
    {
        $xem = (bool) $this->option('xem');
        $doi = $hong = [];
        $soBai = 0;

        foreach (self::LOAI as $lop => $nhan) {
            $lop::query()->chunkById(100, function ($ds) use ($nhan, $xem, &$doi, &$hong, &$soBai) {
                foreach ($ds as $m) {
                    $ten = $nhan.': '.($m->title ?? $m->name ?? '#'.$m->getKey());
                    $coDoi = false;
                    foreach ($m->truongNoiDung() as $truong) {
                        $kq = SuaLinkNoiDung::sua($m->getAttribute($truong));
                        foreach ($kq['doi'] as $d) {
                            $doi[] = [$ten, $d['cu'], $d['moi']];
                        }
                        foreach ($kq['hong'] as $h) {
                            $hong[] = [$ten, $h['link'], $h['ma'] === 410 ? 'Trang đã gỡ' : 'Chưa có trang thay thế'];
                        }
                        if ($kq['doi']) {
                            $m->setAttribute($truong, $kq['html']);
                            $coDoi = true;
                        }
                    }
                    if ($coDoi) {
                        $soBai++;
                        if (! $xem) {
                            $m->saveQuietly();
                        }
                    }
                }
            });
        }

        if ($doi) {
            $this->table(['Bài', 'Link cũ', 'Link mới'], $doi);
        }
        $this->info(($xem ? 'Sẽ sửa ' : 'Đã sửa ').count($doi).' link trong '.$soBai.' bài.'.($xem && $doi ? ' Chạy lại không có --xem để lưu.' : ''));
        if ($hong) {
            $this->newLine();
            $this->warn('Link tới trang không còn — cần sửa tay trong admin (đổi sang trang khác hoặc bỏ link):');
            $this->table(['Bài', 'Link', 'Tình trạng'], $hong);
        }

        return self::SUCCESS;
    }
}
