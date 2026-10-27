<?php

namespace App\Services;

use App\Models\Redirect;
use Modules\Guide\Models\Guide;
use Modules\Maldives\Models\MaldivesCombo;
use Modules\Tour\Models\Tour;

/**
 * Nhân viên đổi đường dẫn (slug) tour / bài cẩm nang / combo Maldives đã đăng → ghi chuyển
 * hướng 301 từ link cũ sang link mới vào bảng "Chuyển hướng link cũ". Link
 * khách đã chia sẻ, link Google đang xếp hạng vẫn mở được, thứ hạng chuyển sang
 * link mới. Website tải danh sách() và chuyển ngay ở proxy, trước khi dựng trang.
 */
class DoiDuongDan
{
    /** Tiền tố trang website mới mà link cũ có thể đổi → loại trang */
    public const TIEN_TO = ['/tour-nuoc-ngoai/' => 'abroad', '/tour-trong-nuoc/' => 'domestic', '/cam-nang/' => 'cam_nang', '/maldives/' => 'maldives'];

    /**
     * Mọi chuyển hướng 301 từ trang tour / cẩm nang: [link cũ => link mới]. Bỏ
     * link cũ nay lại là một trang đang hiện (tour mới đặt trùng đường dẫn cũ)
     * — kẻo chuyển khách khỏi chính trang đó.
     *
     * @return array<string, string>
     */
    public static function danhSach(): array
    {
        $ds = Redirect::query()->where('status_code', 301)->whereNotNull('new_path')
            ->where(function ($q) {
                foreach (array_keys(self::TIEN_TO) as $tienTo) {
                    $q->orWhere('old_path', 'like', $tienTo.'%');
                }
            })
            ->pluck('new_path', 'old_path')->all();
        if (! $ds) {
            return [];
        }

        $slug = fn (string $tienTo) => array_map(fn ($p) => substr($p, strlen($tienTo)),
            array_filter(array_keys($ds), fn ($p) => str_starts_with($p, $tienTo)));
        foreach (self::TIEN_TO as $tienTo => $loai) {
            $con = match ($loai) {
                'cam_nang' => Guide::query()->dangHienThi()->whereIn('slug', $slug($tienTo))->pluck('slug'),
                'maldives' => MaldivesCombo::query()->dangBan()->whereIn('slug', $slug($tienTo))->pluck('slug'),
                default => Tour::query()->published()->where('type', $loai)->whereIn('slug', $slug($tienTo))->pluck('slug'),
            };
            foreach ($con as $s) {
                unset($ds[$tienTo.$s]);
            }
        }

        return $ds;
    }

    public static function ghi(string $cu, string $moi): void
    {
        if ($cu === $moi) {
            return;
        }

        // Đổi lại về đường dẫn cũ: bỏ dòng chuyển hướng đang trỏ ra khỏi nó (khỏi vòng lặp)
        Redirect::query()->where('old_path', $moi)->delete();
        // Link cũ hơn nữa đang trỏ tới link vừa đổi → trỏ thẳng link mới, khỏi chuyển hai lần
        Redirect::query()->where('status_code', 301)->where('new_path', $cu)->update(['new_path' => $moi]);

        Redirect::query()->updateOrCreate(
            ['old_path' => $cu],
            ['new_path' => $moi, 'status_code' => 301, 'source' => 'doi_slug', 'note' => 'Tự ghi khi đổi đường dẫn ('.now()->format('d/m/Y').')'],
        );
    }
}
