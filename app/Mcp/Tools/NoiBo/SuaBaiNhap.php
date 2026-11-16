<?php

namespace App\Mcp\Tools\NoiBo;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Modules\Guide\Models\Guide;

#[Name('update_guide_draft')]
#[Title('Sửa bài cẩm nang nháp')]
#[Description('Sửa một bài cẩm nang đang ở trạng thái NHÁP: chỉ truyền những ô cần đổi. noi_dung_markdown thay TOÀN BỘ nội dung cũ — muốn sửa một đoạn thì đọc bài bằng get_guide trước. Bài đã đăng hoặc đã ẩn không sửa được qua đây (sửa trong admin).')]
#[IsDestructive(false)]
#[IsIdempotent]
class SuaBaiNhap extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required()->description('id bài (từ create_guide_draft hoặc search_guides_admin)'),
            ...collect((new TaoBaiNhap)->schema($schema))
                ->map(fn ($kieu) => $kieu->required(false))
                ->all(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $d = $request->validate(['id' => ['required', 'integer'], ...BaiNhap::luat(false)], BaiNhap::THONG_BAO);

        $bai = Guide::find($d['id']);
        if (! $bai) {
            return Response::error("Không có bài id {$d['id']}.");
        }
        if ($loi = BaiNhap::thieuQuyen($request->user(), 'update', $bai)) {
            return Response::error($loi);
        }
        if ($bai->status !== 'draft') {
            return Response::error('Bài "'.$bai->title.'" đang '.mb_strtolower(BaiNhap::TRANG_THAI[$bai->status] ?? $bai->status).' — chỉ sửa được bài nháp qua MCP. Sửa trong admin: '.BaiNhap::tomTat($bai)['link_sua_trong_admin']);
        }

        unset($d['id']);
        if ($d === []) {
            return Response::error('Chưa có ô nào cần sửa.');
        }
        [$a, $loi] = BaiNhap::thuocTinh($d, $bai);
        if ($loi) {
            return Response::error($loi);
        }

        $bai->update($a);

        return Response::structured(['da_sua' => array_keys($d), ...BaiNhap::tomTat($bai->fresh())]);
    }
}
