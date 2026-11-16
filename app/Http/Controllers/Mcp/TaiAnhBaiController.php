<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TepTaiLen;
use App\Services\ThuNhoAnh;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Guide\Models\Guide;

/**
 * POST /mcp/noi-bo/tai-anh?ma=…&signature=… — nhận tệp ảnh trợ lý AI gửi lên từ
 * máy nhân viên (link do công cụ request_image_upload cấp: có chữ ký, dùng một
 * lần, 15 phút). Lưu vào ổ public giống ảnh tải trong admin, thu nhỏ nếu quá to.
 */
class TaiAnhBaiController extends Controller
{
    private const DUOI = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

    public function __invoke(Request $request): JsonResponse
    {
        $khoa = 'mcp-tai-anh:'.$request->query('ma');
        $phien = Cache::get($khoa);
        if (! is_array($phien)) {
            return response()->json(['message' => 'Link đã dùng hoặc hết hạn — gọi lại request_image_upload để lấy link mới.'], 410);
        }

        $request->validate(
            ['anh' => ['required', 'file', 'max:10240', 'mimetypes:'.implode(',', TepTaiLen::ANH)]],
            ['anh.max' => 'Ảnh tối đa 10 MB.', 'anh.mimetypes' => 'Chỉ nhận ảnh JPG, PNG, WebP, GIF.', 'anh.required' => 'Thiếu tệp ảnh (trường "anh").'],
        );

        // Người cấp link phải còn là Admin, còn quyền sửa cẩm nang; bài phải còn là nháp
        $bai = Guide::find($phien['bai']);
        $nguoi = User::find($phien['nguoi']);
        if (! $nguoi?->laAdmin() || ! $bai || $bai->status !== 'draft' || ! $nguoi->can('update', $bai)) {
            Cache::forget($khoa);

            return response()->json(['message' => 'Bài không còn là nháp hoặc tài khoản không còn quyền sửa cẩm nang.'], 403);
        }
        if (! Cache::pull($khoa)) {
            return response()->json(['message' => 'Link đã dùng.'], 410);
        }

        $tep = $request->file('anh');
        $duoi = self::DUOI[$tep->getMimeType()] ?? 'jpg';
        $thuMuc = $phien['loai'] === 'bia' ? 'guides' : 'guides/noi-dung';
        $duongDan = $tep->storeAs($thuMuc, Str::lower(Str::random(32)).'.'.$duoi, 'public');
        ThuNhoAnh::xuLy(Storage::disk('public')->path($duongDan));

        $url = Storage::disk('public')->url($duongDan);
        if ($phien['loai'] === 'bia') {
            $bai->update(['cover_image' => $duongDan]);
        }

        return response()->json([
            'url' => $url,
            'loai' => $phien['loai'],
            'markdown' => '!['.str_replace([']', '['], '', (string) $phien['alt']).']('.$url.')',
            'message' => $phien['loai'] === 'bia' ? 'Đã gắn ảnh bìa cho bài nháp.' : 'Đã tải ảnh — chèn đoạn markdown vào nội dung rồi gọi update_guide_draft.',
        ], 201, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); // đọc được ngay trên cửa sổ dòng lệnh
    }
}
