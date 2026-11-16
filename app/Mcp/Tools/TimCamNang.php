<?php

namespace App\Mcp\Tools;

use App\Mcp\HoTro;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Modules\Guide\Models\Guide;

/** Bài cẩm nang du lịch đã đăng (/cam-nang): kinh nghiệm, lịch trình, mẹo. */
#[Name('search_guides')]
#[Title('Tìm bài cẩm nang du lịch')]
#[Description('Tìm bài cẩm nang du lịch PSV Travel đã đăng (kinh nghiệm, lịch trình tự túc, thời điểm đẹp, ẩm thực, visa…) theo từ khoá. Trả về tiêu đề, tóm tắt, ngày đăng và link bài. Search PSV Travel travel guides and tips.')]
#[IsReadOnly]
#[IsIdempotent]
class TimCamNang extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'tu_khoa' => $schema->string()->max(100)
                ->description('Từ khoá, VD "Đà Lạt", "visa Nhật", "kinh nghiệm Bali". Bỏ trống để lấy bài mới nhất.'),
            'so_ket_qua' => $schema->integer()->min(1)->max(20)->default(5)
                ->description('Số bài trả về tối đa (mặc định 5)'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $d = $request->validate([
            'tu_khoa' => ['nullable', 'string', 'max:100'],
            'so_ket_qua' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $ds = Guide::query()->dangHienThi()
            ->orderByRaw('CASE WHEN published_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('published_at')->orderByDesc('id')
            ->limit(500)
            ->get(['id', 'slug', 'title', 'excerpt', 'category', 'published_at'])
            ->filter(fn (Guide $g) => HoTro::khop($d['tu_khoa'] ?? null, $g->title, (string) $g->excerpt))
            ->values();

        return Response::structured([
            'so_bai_tim_thay' => $ds->count(),
            'bai_viet' => $ds->take($d['so_ket_qua'] ?? 5)->map(fn (Guide $g) => [
                'tieu_de' => $g->title,
                'tom_tat' => HoTro::chu($g->excerpt, 300),
                'ngay_dang' => $g->published_at?->format('Y-m-d'),
                'url' => HoTro::web('cam-nang/'.$g->slug),
            ])->values()->all(),
            'xem_tat_ca' => HoTro::web('cam-nang'),
        ]);
    }
}
