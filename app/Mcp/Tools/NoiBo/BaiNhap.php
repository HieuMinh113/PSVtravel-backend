<?php

namespace App\Mcp\Tools\NoiBo;

use App\Filament\Resources\Guides\GuideResource;
use App\Mcp\HoTro;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\YouTube;
use Illuminate\Support\Str;
use Modules\Guide\Models\Guide;
use Modules\Tour\Models\Tour;

/**
 * Phần dùng chung của các công cụ bài cẩm nang trong MCP nội bộ: kiểm tra dữ
 * liệu, chuyển Markdown → HTML, ghi bài. Bài tạo / sửa qua MCP LUÔN là nháp —
 * người duyệt vào admin xem lại, chọn ảnh rồi mới bấm Đăng.
 */
class BaiNhap
{
    public const TRANG_THAI = ['draft' => 'Nháp', 'published' => 'Đã đăng', 'hidden' => 'Đã ẩn'];

    /** Luật kiểm tra chung; $taoMoi = true thì tiêu đề và nội dung bắt buộc */
    public static function luat(bool $taoMoi): array
    {
        $bat = $taoMoi ? 'required' : 'sometimes';

        return [
            'tieu_de' => [$bat, 'string', 'min:10', 'max:255'],
            'noi_dung_markdown' => [$bat, 'string', 'min:200', 'max:100000'],
            'tom_tat' => ['nullable', 'string', 'max:500'],
            'tieu_de_seo' => ['nullable', 'string', 'max:70'],
            'chuyen_muc' => ['nullable', 'in:'.implode(',', array_keys(Guide::CHUYEN_MUC))],
            'duong_dan' => ['nullable', 'string', 'max:200'],
            'tac_gia_id' => ['nullable', 'integer'],
            'tour_slug' => ['nullable', 'string', 'max:200'],
            'video_youtube' => ['nullable', 'string', 'max:255', YouTube::quyTac()],
        ];
    }

    public const THONG_BAO = [
        'noi_dung_markdown.min' => 'Nội dung quá ngắn (ít nhất 200 ký tự).',
        'tieu_de.min' => 'Tiêu đề quá ngắn (ít nhất 10 ký tự).',
        'tieu_de_seo.max' => 'Tiêu đề SEO tối đa 70 ký tự (nên 50–60).',
        'tom_tat.max' => 'Tóm tắt tối đa 500 ký tự (nên 120–160).',
        'chuyen_muc.in' => 'Chuyên mục không hợp lệ — xem guide_options.',
    ];

    /**
     * Dữ liệu đã kiểm tra → thuộc tính của Guide. Trả về [thuộc tính, lỗi].
     *
     * @return array{0: array<string, mixed>, 1: ?string}
     */
    public static function thuocTinh(array $d, ?Guide $bai = null): array
    {
        $a = [];
        if (array_key_exists('tieu_de', $d)) {
            $a['title'] = trim($d['tieu_de']);
        }
        if (array_key_exists('noi_dung_markdown', $d)) {
            $a['content'] = HoTro::htmlTuMarkdown($d['noi_dung_markdown']);
        }
        foreach (['tom_tat' => 'excerpt', 'tieu_de_seo' => 'seo_title', 'chuyen_muc' => 'category', 'video_youtube' => 'video_url'] as $vao => $cot) {
            if (array_key_exists($vao, $d)) {
                $a[$cot] = filled($d[$vao]) ? trim($d[$vao]) : null;
            }
        }

        if (array_key_exists('tac_gia_id', $d)) {
            if ($d['tac_gia_id'] && ! TeamMember::query()->dangHienThi()->whereKey($d['tac_gia_id'])->exists()) {
                return [[], 'Không có tác giả id '.$d['tac_gia_id'].' — xem danh sách tác giả ở guide_options.'];
            }
            $a['team_member_id'] = $d['tac_gia_id'] ?: null;
        }

        if (array_key_exists('tour_slug', $d)) {
            $tourId = null;
            if (filled($d['tour_slug'])) {
                $tourId = Tour::query()->where('slug', $d['tour_slug'])->value('id');
                if (! $tourId) {
                    return [[], "Không có tour \"{$d['tour_slug']}\" — dùng search_tours để lấy slug đúng."];
                }
            }
            $a['tour_id'] = $tourId;
        }

        // Đường dẫn: tự sinh từ tiêu đề khi tạo mới; trùng thì thêm -2, -3…
        if (filled($d['duong_dan'] ?? null) || (! $bai && isset($a['title']))) {
            $a['slug'] = self::duongDanTrong(Str::slug(filled($d['duong_dan'] ?? null) ? $d['duong_dan'] : $a['title']), $bai?->id);
            if ($a['slug'] === '') {
                return [[], 'Đường dẫn không hợp lệ.'];
            }
        }

        return [$a, null];
    }

    private static function duongDanTrong(string $goc, ?int $boQua): string
    {
        $slug = Str::limit($goc, 190, '');
        for ($i = 2; Guide::withTrashed()->where('slug', $slug)->when($boQua, fn ($q) => $q->whereKeyNot($boQua))->exists(); $i++) {
            $slug = Str::limit($goc, 185, '').'-'.$i;
        }

        return $slug;
    }

    /**
     * Quyền với bài cẩm nang theo đúng phân quyền trong admin (Vai trò → Cẩm nang):
     * ngoài vai trò Admin, tài khoản phải được tick quyền tương ứng. null = được phép.
     */
    public static function thieuQuyen(?User $user, string $hanhDong, ?Guide $bai = null): ?string
    {
        $duoc = $bai ? $user?->can($hanhDong, $bai) : $user?->can($hanhDong, Guide::class);

        return $duoc ? null : 'Tài khoản của bạn chưa có quyền "'.match ($hanhDong) {
            'create' => 'tạo',
            'update' => 'sửa',
            default => 'xem',
        }.'" cẩm nang trong admin. Nhờ người quản trị tick quyền ở Hệ thống → Vai trò.';
    }

    /** Thông tin một bài trả về cho AI (không kèm nội dung) */
    public static function tomTat(Guide $g): array
    {
        return [
            'id' => $g->id,
            'tieu_de' => $g->title,
            'trang_thai' => self::TRANG_THAI[$g->status] ?? $g->status,
            'chuyen_muc' => Guide::CHUYEN_MUC[$g->category] ?? $g->category,
            'duong_dan' => $g->slug,
            'co_anh_bia' => filled($g->cover_image),
            'cap_nhat' => $g->updated_at?->format('Y-m-d H:i'),
            'link_web' => $g->status === 'published' ? HoTro::web('cam-nang/'.$g->slug) : null,
            'link_sua_trong_admin' => GuideResource::getUrl('edit', ['record' => $g->id], panel: 'admin'),
        ];
    }
}
