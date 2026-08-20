<?php

namespace Modules\Banner\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Banner\Models\Banner;
use Modules\Banner\Transformers\BannerResource;

class BannerApiController extends Controller
{
    // GET /api/v1/banners?position=promo — chỉ banner đang thực sự hiển thị.
    // Không truyền position thì mặc định lấy banner khuyến mãi, giữ nguyên
    // hành vi cũ cho các trang đã gọi sẵn.
    public function index(Request $request)
    {
        $viTri = (string) $request->query('position', 'promo');

        // Chỉ chấp nhận vị trí có trong danh sách khai báo — chặn dò bảng
        // bằng tham số tuỳ ý từ bên ngoài.
        if (! array_key_exists($viTri, Banner::VI_TRI)) {
            $viTri = 'promo';
        }

        $banners = Banner::query()->viTri($viTri)->dangHienThi()->get();

        return BannerResource::collection($banners);
    }
}