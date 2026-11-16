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
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Modules\Guide\Models\Guide;

#[Name('guide_options')]
#[Title('Chuyên mục & tác giả cho bài cẩm nang')]
#[Description('Danh sách mã chuyên mục và tác giả (id) dùng khi tạo bài, cùng hướng dẫn viết bài cho website PSV Travel.')]
#[IsReadOnly]
#[IsIdempotent]
class LuaChonBai extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();

        return Response::structured([
            'nguoi_dang_nhap' => $user?->name,
            'chuyen_muc' => Guide::CHUYEN_MUC,
            'tac_gia' => TeamMember::query()->dangHienThi()->orderBy('sort_order')->get(['id', 'name', 'position', 'user_id'])
                ->map(fn ($t) => ['id' => $t->id, 'ten' => $t->name, 'chuc_danh' => $t->position, 'la_ban' => $user && $t->user_id === $user->id])
                ->values()->all(),
            'huong_dan_viet' => [
                'Viết tiếng Việt có dấu, câu ngắn, mỗi mục ## trả lời một câu hỏi khách hay hỏi.',
                'Giá, lịch khởi hành, số chỗ: lấy từ search_tours / get_tour, không tự đoán. Không bịa số liệu, giải thưởng hay chính sách.',
                'Chèn link nội bộ tới tour liên quan (url từ search_tours) và bài đã đăng (search_guides_admin).',
                'Tóm tắt 120–160 ký tự; tiêu đề SEO 50–60 ký tự có từ khoá chính.',
                'Chỉ dùng ảnh do PSV chụp hoặc có quyền dùng.',
            ],
        ]);
    }
}
