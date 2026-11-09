<?php

namespace App\Services;

use App\Models\EventReview;
use Illuminate\Support\Facades\Storage;
use Modules\Page\Models\Setting;

/**
 * Nội dung trang giới thiệu Team Building (/team-building trên website) do
 * Marketing nhập ở Admin → Nội dung website → Trang team building.
 *
 * Lưu một khối JSON trong bảng settings (khoá trang_team_building, nhóm riêng
 * — KHÔNG nằm trong nhóm cài đặt công khai). Website đọc qua
 * GET /api/v1/team-building-page. Ô nào để trống thì website dùng chữ / số
 * mặc định đã công bố; khối nào không có dữ liệu (logo khách hàng, khoảnh
 * khắc, đánh giá) thì website tự ẩn.
 */
class NoiDungTeamBuilding
{
    public const KHOA = 'trang_team_building';

    public const THU_MUC = 'team-building';

    /** @return array<string, mixed> */
    public static function lay(): array
    {
        $d = json_decode((string) Setting::lay(self::KHOA), true);

        return is_array($d) ? $d : [];
    }

    /** @param  array<string, mixed>  $d */
    public static function luu(array $d): void
    {
        $cu = self::cacTep(self::lay());
        Setting::updateOrCreate(['key' => self::KHOA], [
            'value' => json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'group' => 'team_building',
            'type' => 'json',
            'label' => 'Nội dung trang Team Building',
            'sort_order' => 990,
        ]);
        // Ảnh đã gỡ khỏi trang thì xoá khỏi ổ — chỉ trong thư mục của trang này
        $conDung = self::cacTep($d);
        foreach (array_diff($cu, $conDung) as $tep) {
            if (str_starts_with($tep, self::THU_MUC.'/')) {
                Storage::disk('public')->delete($tep);
            }
        }
    }

    /**
     * Dữ liệu cho website: đường dẫn ảnh đầy đủ, mã video YouTube, chỉ logo đang
     * bật, kèm đánh giá đã duyệt mới nhất của các gói team building.
     *
     * @return array<string, mixed>
     */
    public static function congKhai(): array
    {
        $d = self::lay();
        $chu = fn ($v) => is_string($v) && trim($v) !== '' ? trim($v) : null;
        $hero = $d['hero'] ?? [];
        $gt = $d['gioi_thieu'] ?? [];

        return [
            'hero' => [
                'anh' => self::url($hero['anh'] ?? null),
                'video_id' => YouTube::id($hero['video_url'] ?? null),
                'tieu_de' => $chu($hero['tieu_de'] ?? null),
                'mo_ta' => $chu($hero['mo_ta'] ?? null),
            ],
            'gioi_thieu' => [
                'anh' => self::url($gt['anh'] ?? null),
                'tieu_de' => $chu($gt['tieu_de'] ?? null),
                'noi_dung' => $chu($gt['noi_dung'] ?? null),
            ],
            'so_lieu' => collect($d['so_lieu'] ?? [])
                ->filter(fn ($s) => $chu($s['so'] ?? null) && $chu($s['nhan'] ?? null))
                ->map(fn ($s) => ['so' => $chu($s['so']), 'nhan' => $chu($s['nhan'])])
                ->values()->all(),
            'loai_hinh' => collect($d['loai_hinh'] ?? [])
                ->filter(fn ($l) => $chu($l['ten'] ?? null))
                ->map(fn ($l) => ['ten' => $chu($l['ten']), 'mo_ta' => $chu($l['mo_ta'] ?? null), 'anh' => self::url($l['anh'] ?? null)])
                ->values()->all(),
            'khoanh_khac' => collect($d['khoanh_khac'] ?? [])
                ->map(fn ($p) => self::url($p))
                ->filter()->values()->all(),
            'khach_hang' => collect($d['khach_hang'] ?? [])
                ->filter(fn ($k) => ! empty($k['hien']) && $chu($k['ten'] ?? null) && self::url($k['logo'] ?? null))
                ->map(fn ($k) => ['ten' => $chu($k['ten']), 'logo' => self::url($k['logo'])])
                ->values()->all(),
            'danh_gia' => EventReview::query()
                ->daDuyet()
                ->whereNotNull('content')
                ->whereHas('event', fn ($q) => $q->dangHienThi())
                ->with('event:id,slug,title')
                ->latest()
                ->take(6)
                ->get()
                ->map(fn (EventReview $r) => [
                    'ten' => $r->customer_name,
                    'diem' => (int) $r->rating,
                    'noi_dung' => $r->content,
                    'goi' => $r->event?->title,
                    'goi_slug' => $r->event?->slug,
                ])->all(),
        ];
    }

    private static function url($duong): ?string
    {
        if (! is_string($duong) || trim($duong) === '') {
            return null;
        }

        return str_starts_with($duong, 'http') ? $duong : asset('storage/'.ltrim($duong, '/'));
    }

    /** Mọi đường dẫn ảnh đang dùng trong nội dung. @return list<string> */
    private static function cacTep(array $d): array
    {
        $tep = [$d['hero']['anh'] ?? null, $d['gioi_thieu']['anh'] ?? null];
        foreach ($d['loai_hinh'] ?? [] as $l) {
            $tep[] = $l['anh'] ?? null;
        }
        foreach ($d['khach_hang'] ?? [] as $k) {
            $tep[] = $k['logo'] ?? null;
        }
        foreach ($d['khoanh_khac'] ?? [] as $p) {
            $tep[] = $p;
        }

        return array_values(array_filter($tep, fn ($t) => is_string($t) && $t !== ''));
    }
}
