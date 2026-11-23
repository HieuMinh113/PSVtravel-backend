<?php

namespace App\Mcp\Tools\NoiBo;

use App\Filament\Resources\Reviews\ReviewResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Modules\Review\Models\Review;

#[Name('search_reviews')]
#[Title('Xem đánh giá tour của khách')]
#[Description('Đánh giá tour khách gửi: số sao, nội dung, trạng thái duyệt, phản hồi của công ty. Lọc theo trạng thái, số sao tối đa (vd 3 = các đánh giá chưa hài lòng), tên tour. Chỉ xem — duyệt / phản hồi làm trong admin.')]
#[IsReadOnly]
#[IsIdempotent]
class TimDanhGia extends Tool
{
    private const TRANG_THAI = ['pending' => 'Chờ duyệt', 'approved' => 'Đã duyệt', 'rejected' => 'Từ chối'];

    public function schema(JsonSchema $schema): array
    {
        return [
            'trang_thai' => $schema->string()->enum(array_keys(self::TRANG_THAI))->description('pending = chờ duyệt, approved = đã duyệt, rejected = từ chối'),
            'sao_toi_da' => $schema->integer()->min(1)->max(5)->description('Chỉ đánh giá từ số sao này trở xuống'),
            'tour' => $schema->string()->max(100)->description('Một phần tên tour'),
            'so_ket_qua' => $schema->integer()->min(1)->max(50)->default(20),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($loi = QuanTri::thieuQuyen($request->user(), 'viewAny', 'xem đánh giá', Review::class)) {
            return Response::error($loi);
        }
        $d = $request->validate([
            'trang_thai' => ['nullable', 'in:'.implode(',', array_keys(self::TRANG_THAI))],
            'sao_toi_da' => ['nullable', 'integer', 'min:1', 'max:5'],
            'tour' => ['nullable', 'string', 'max:100'],
            'so_ket_qua' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $q = Review::query()
            ->when(isset($d['trang_thai']), fn ($q) => $q->where('status', $d['trang_thai']))
            ->when(isset($d['sao_toi_da']), fn ($q) => $q->where('rating', '<=', $d['sao_toi_da']))
            ->when(filled($d['tour'] ?? null), fn ($q) => $q->whereHas('tour', fn ($t) => QuanTri::timTuKhoa($t, $d['tour'], ['name'])));

        $tong = (clone $q)->count();
        $ds = $q->with('tour:id,name')->latest('id')->limit($d['so_ket_qua'] ?? 20)->get();

        return Response::structured([
            'so_danh_gia' => $tong,
            'danh_gia' => $ds->map(fn (Review $r) => [
                'id' => $r->id,
                'ngay' => QuanTri::ngay($r->created_at),
                'tour' => $r->tour?->name,
                'khach' => $r->customer_name,
                'so_sao' => $r->rating,
                'noi_dung' => Str::limit((string) $r->content, 1500),
                'trang_thai' => self::TRANG_THAI[$r->status] ?? $r->status,
                'phan_hoi_cua_cong_ty' => $r->admin_reply,
                'link_admin' => ReviewResource::getUrl('view', ['record' => $r->id], panel: 'admin'),
            ])->all(),
        ]);
    }
}
