<?php

namespace App\Console\Commands;

use App\Models\Redirect;
use App\Services\LinkCu;
use Illuminate\Console\Command;

/**
 * Nhập danh sách link cũ (lấy từ nhật ký nginx: các đường dẫn bị 404) để máy
 * khớp sẵn trang mới, nhân viên thấy ngay trong admin (Hệ thống → Chuyển hướng
 * link cũ) mà không phải chờ khách / Google mở lại từng link.
 *
 * Mỗi dòng là "số_lượt đường_dẫn" (đầu ra của `uniq -c`) hoặc chỉ "đường_dẫn".
 * Thường chạy qua scripts/nhap-link-cu.sh; tự chạy:
 *
 *   ... | docker compose -f docker-compose.prod.yml exec -T app php artisan psv:link-cu
 *   docker compose -f docker-compose.prod.yml exec -T app php artisan psv:link-cu --xem < danh-sach.txt
 */
class NhapLinkCu extends Command
{
    protected $signature = 'psv:link-cu
        {tep? : Tệp danh sách (bỏ trống thì đọc từ đầu vào chuẩn)}
        {--xem : Chỉ xem kết quả khớp, không lưu}';

    protected $description = 'Khớp danh sách link của website cũ sang trang mới (lưu vào bảng chuyển hướng)';

    public function handle(): int
    {
        $tep = $this->argument('tep');
        if ($tep !== null && ! is_readable($tep)) {
            $this->error("Không đọc được tệp {$tep}");

            return self::FAILURE;
        }
        $dong = $tep !== null ? file($tep, FILE_IGNORE_NEW_LINES) : $this->docDauVao();

        // Gộp các dòng cùng đường dẫn sau chuẩn hoá (/Nhat-Ban/ = /nhat-ban)
        $ds = [];
        foreach ($dong as $d) {
            if (! preg_match('#^\s*(?:(\d+)\s+)?(\S+)\s*$#', $d, $m)) {
                continue;
            }
            $p = LinkCu::chuanHoa($m[2]);
            if ($p !== null) {
                $ds[$p] = ($ds[$p] ?? 0) + max(1, (int) $m[1]);
            }
        }
        if (! $ds) {
            $this->warn('Không có đường dẫn nào. Mỗi dòng: "số_lượt /đường/dẫn" hoặc "/đường/dẫn".');

            return self::SUCCESS;
        }
        arsort($ds);

        $xem = (bool) $this->option('xem');
        $tong = [301 => [0, 0], 410 => [0, 0], 404 => [0, 0]];
        $bang = [];
        $boQua = 0;
        foreach ($ds as $p => $luot) {
            [$kq, $luu] = LinkCu::xem($p);
            if (! $luu) {
                // Ảnh, feed, link dò bừa của bot: website tự xử lý, không lưu
                $boQua++;

                continue;
            }
            if (! $xem) {
                // Link mới: lượt trong nhật ký thành "Lượt mở" để xếp ưu tiên ngay.
                // Link đã có thì không cộng (chạy lại lệnh không bị đếm đôi).
                $kq = LinkCu::tra($p, Redirect::query()->where('old_path', $p)->exists() ? 0 : $luot);
            }
            $tong[$kq['ma']][0]++;
            $tong[$kq['ma']][1] += $luot;
            $bang[] = [$luot, $p, match ($kq['ma']) {
                301 => '→ '.$kq['toi'],
                410 => 'đã gỡ (410)',
                default => 'CHƯA KHỚP',
            }];
        }

        $this->table(['Lượt', 'Link cũ', 'Kết quả'], array_slice($bang, 0, 200));
        if (count($bang) > 200) {
            $this->line('… và '.(count($bang) - 200).' link nữa (xem đủ trong admin).');
        }
        $this->newLine();
        $this->info(sprintf('Chuyển sang trang mới: %d link (%d lượt)', ...$tong[301]));
        $this->line(sprintf('Đã gỡ hẳn (410):       %d link (%d lượt)', ...$tong[410]));
        $this->line(sprintf('Chưa khớp:             %d link (%d lượt) — chọn trang thay thế trong admin', ...$tong[404]));
        if ($boQua) {
            $this->line("Bỏ qua {$boQua} đường dẫn ảnh / feed / link bot dò bừa (website tự trả 410 hoặc 404).");
        }
        $this->line($xem ? 'Chế độ --xem: CHƯA lưu gì.' : 'Đã lưu — xem / sửa ở Hệ thống → Chuyển hướng link cũ.');

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function docDauVao(): array
    {
        if (function_exists('posix_isatty') && posix_isatty(STDIN)) {
            return [];
        }

        return explode("\n", (string) stream_get_contents(STDIN));
    }
}
