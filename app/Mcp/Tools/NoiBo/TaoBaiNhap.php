<?php

namespace App\Mcp\Tools\NoiBo;

use App\Models\TeamMember;
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

#[Name('create_guide_draft')]
#[Title('Tạo bài cẩm nang nháp')]
#[Description('Tạo một bài cẩm nang mới ở trạng thái NHÁP (không hiện trên website). Nội dung viết bằng Markdown: dùng ## / ### cho mục, không dùng # (tên bài đã là tiêu đề chính). Có thể đọc nội dung từ file trên máy rồi truyền vào. Sau khi tạo, người duyệt vào link_sua_trong_admin để xem lại, chọn ảnh bìa và bấm Đăng. Muốn thêm ảnh từ máy: dùng request_image_upload.')]
#[IsDestructive(false)]
#[IsIdempotent(false)]
class TaoBaiNhap extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'tieu_de' => $schema->string()->min(10)->max(255)->required()->description('Tên bài, VD "Kinh nghiệm du lịch Đà Lạt 3 ngày 2 đêm tự túc"'),
            'noi_dung_markdown' => $schema->string()->min(200)->required()->description('Toàn bộ nội dung bài bằng Markdown (## mục, ### mục con, danh sách, bảng, link). Không dùng HTML.'),
            'tom_tat' => $schema->string()->max(500)->description('Tóm tắt 120–160 ký tự, hiện ở danh sách bài và dưới tiêu đề trên Google'),
            'tieu_de_seo' => $schema->string()->max(70)->description('Tiêu đề hiện trên Google, 50–60 ký tự, có từ khoá chính. Bỏ trống = dùng tên bài'),
            'chuyen_muc' => $schema->string()->enum(array_keys(Guide::CHUYEN_MUC))->description('Mã chuyên mục: '.implode(', ', array_map(fn ($k, $v) => "$k = $v", array_keys(Guide::CHUYEN_MUC), Guide::CHUYEN_MUC))),
            'duong_dan' => $schema->string()->max(200)->description('Đường dẫn (slug) không dấu. Bỏ trống = tự tạo từ tên bài'),
            'tac_gia_id' => $schema->integer()->description('id tác giả hiện trên bài (xem guide_options). Bỏ trống = tác giả gắn với tài khoản của bạn, nếu có'),
            'tour_slug' => $schema->string()->max(200)->description('Slug tour liên quan (từ search_tours) để bài hiện ô đặt tour'),
            'video_youtube' => $schema->string()->max(255)->description('Link video YouTube minh hoạ (không bắt buộc)'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($loi = BaiNhap::thieuQuyen($request->user(), 'create')) {
            return Response::error($loi);
        }
        $d = $request->validate(BaiNhap::luat(true), BaiNhap::THONG_BAO);
        [$a, $loi] = BaiNhap::thuocTinh($d);
        if ($loi) {
            return Response::error($loi);
        }

        $user = $request->user();
        $bai = Guide::create([
            ...$a,
            'status' => 'draft',
            'author_id' => $user?->id,
            'team_member_id' => $a['team_member_id']
                ?? TeamMember::query()->dangHienThi()->where('user_id', $user?->id)->value('id'),
        ]);

        return Response::structured([
            'da_tao' => true,
            ...BaiNhap::tomTat($bai->fresh()),
            'buoc_tiep' => 'Bài đang ở trạng thái Nháp. Mở link_sua_trong_admin để xem lại, thêm ảnh bìa (hoặc dùng request_image_upload) rồi bấm Đăng.',
        ]);
    }
}
