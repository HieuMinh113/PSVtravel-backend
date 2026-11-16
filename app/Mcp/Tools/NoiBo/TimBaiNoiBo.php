<?php

namespace App\Mcp\Tools\NoiBo;

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

#[Name('search_guides_admin')]
#[Title('Tìm bài cẩm nang (cả nháp)')]
#[Description('Tìm bài cẩm nang trong admin theo từ khoá (không dấu cũng được), gồm cả bài nháp và bài ẩn. Dùng trước khi viết để tránh trùng chủ đề và lấy link bài đã đăng để chèn link nội bộ.')]
#[IsReadOnly]
#[IsIdempotent]
class TimBaiNoiBo extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'tu_khoa' => $schema->string()->max(100)->description('Từ khoá trong tên bài hoặc tóm tắt'),
            'trang_thai' => $schema->string()->enum(array_keys(BaiNhap::TRANG_THAI))->description('draft = nháp, published = đã đăng, hidden = đã ẩn'),
            'so_ket_qua' => $schema->integer()->min(1)->max(30)->default(10),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($loi = BaiNhap::thieuQuyen($request->user(), 'viewAny')) {
            return Response::error($loi);
        }
        $d = $request->validate([
            'tu_khoa' => ['nullable', 'string', 'max:100'],
            'trang_thai' => ['nullable', 'in:'.implode(',', array_keys(BaiNhap::TRANG_THAI))],
            'so_ket_qua' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $ds = Guide::query()
            ->when(isset($d['trang_thai']), fn ($q) => $q->where('status', $d['trang_thai']))
            ->latest('updated_at')->limit(1000)
            ->get(['id', 'title', 'slug', 'excerpt', 'category', 'status', 'cover_image', 'updated_at'])
            ->filter(fn (Guide $g) => HoTro::khop($d['tu_khoa'] ?? null, $g->title, (string) $g->excerpt))
            ->values();

        return Response::structured([
            'so_bai' => $ds->count(),
            'bai' => $ds->take($d['so_ket_qua'] ?? 10)->map(fn (Guide $g) => BaiNhap::tomTat($g))->values()->all(),
        ]);
    }
}
