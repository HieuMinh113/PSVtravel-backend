<?php

namespace App\Mcp\Tools\NoiBo;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Modules\Guide\Models\Guide;

/**
 * Trợ lý AI không thể nhét cả tệp ảnh vào lệnh gọi MCP (quá dài), nên công cụ
 * này cấp một link tải lên dùng MỘT lần, hết hạn sau 15 phút. Trợ lý trên máy
 * nhân viên (Claude Code…) gửi tệp ảnh trong thư mục máy tính lên link đó bằng curl.
 */
#[Name('request_image_upload')]
#[Title('Lấy link tải ảnh lên bài nháp')]
#[Description('Cấp link tải một ảnh từ máy tính lên bài cẩm nang NHÁP: loai = "bia" (ảnh bìa, tự gắn vào bài) hoặc "noi_dung" (ảnh trong bài: kết quả trả về đoạn Markdown để chèn vào noi_dung_markdown rồi gọi update_guide_draft). Link dùng một lần, hết hạn sau 15 phút. Gửi ảnh bằng lệnh curl trong cach_tai. Ảnh JPG/PNG/WebP/GIF, tối đa 10 MB. Chỉ dùng ảnh PSV có quyền sử dụng.')]
#[IsIdempotent(false)]
class TaoLinkTaiAnh extends Tool
{
    public const PHUT_HIEU_LUC = 15;

    public function schema(JsonSchema $schema): array
    {
        return [
            'bai_id' => $schema->integer()->required()->description('id bài nháp'),
            'loai' => $schema->string()->enum(['bia', 'noi_dung'])->required()->description('bia = ảnh bìa, noi_dung = ảnh chèn trong bài'),
            'mo_ta_anh' => $schema->string()->max(150)->description('Mô tả ngắn nội dung ảnh (dùng làm alt), VD "Đồi chè Cầu Đất lúc bình minh"'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $d = $request->validate([
            'bai_id' => ['required', 'integer'],
            'loai' => ['required', 'in:bia,noi_dung'],
            'mo_ta_anh' => ['nullable', 'string', 'max:150'],
        ]);

        $bai = Guide::find($d['bai_id']);
        if (! $bai || $bai->status !== 'draft') {
            return Response::error('Chỉ tải ảnh được cho bài đang ở trạng thái Nháp.');
        }
        if ($loi = BaiNhap::thieuQuyen($request->user(), 'update', $bai)) {
            return Response::error($loi);
        }

        $ma = Str::random(40);
        Cache::put('mcp-tai-anh:'.$ma, [
            'bai' => $bai->id,
            'loai' => $d['loai'],
            'alt' => $d['mo_ta_anh'] ?? $bai->title,
            'nguoi' => $request->user()?->id,
        ], now()->addMinutes(self::PHUT_HIEU_LUC));

        $link = URL::temporarySignedRoute('mcp.noi-bo.tai-anh', now()->addMinutes(self::PHUT_HIEU_LUC), ['ma' => $ma]);

        return Response::structured([
            'link_tai_len' => $link,
            'het_han_sau_phut' => self::PHUT_HIEU_LUC,
            'cach_tai' => 'curl -sS -X POST -F "anh=@<đường dẫn tệp ảnh trên máy>" "'.$link.'"',
            'ket_qua' => $d['loai'] === 'bia'
                ? 'Ảnh tự gắn làm ảnh bìa của bài.'
                : 'Phản hồi có "markdown" — chèn vào nội dung rồi gọi update_guide_draft.',
        ]);
    }
}
