<?php

namespace App\Mcp\Tools\NoiBo;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Storage;
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

#[Name('get_guide')]
#[Title('Xem một bài cẩm nang')]
#[Description('Đọc đầy đủ một bài cẩm nang (cả nháp): tiêu đề, tóm tắt, tiêu đề SEO, chuyên mục, ảnh bìa, nội dung HTML hiện tại. Dùng trước khi sửa bài nháp hoặc để tham khảo văn phong bài đã đăng.')]
#[IsReadOnly]
#[IsIdempotent]
class XemBai extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return ['id' => $schema->integer()->required()->description('id bài')];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($loi = BaiNhap::thieuQuyen($request->user(), 'viewAny')) {
            return Response::error($loi);
        }
        $d = $request->validate(['id' => ['required', 'integer']]);
        $bai = Guide::with(['tacGia:id,name', 'tour:id,slug,name'])->find($d['id']);
        if (! $bai) {
            return Response::error("Không có bài id {$d['id']}.");
        }

        return Response::structured([
            ...BaiNhap::tomTat($bai),
            'tom_tat' => $bai->excerpt,
            'tieu_de_seo' => $bai->seo_title,
            'tac_gia' => $bai->tacGia?->name,
            'tour' => $bai->tour ? ['slug' => $bai->tour->slug, 'ten' => $bai->tour->name] : null,
            'anh_bia' => $bai->cover_image ? Storage::disk('public')->url($bai->cover_image) : null,
            'noi_dung_html' => $bai->content,
        ]);
    }
}
