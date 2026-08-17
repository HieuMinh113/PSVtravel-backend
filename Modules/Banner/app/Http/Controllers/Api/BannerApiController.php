<?php

namespace Modules\Banner\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\Banner\Models\Banner;
use Modules\Banner\Transformers\BannerResource;

class BannerApiController extends Controller
{
    // GET /api/v1/banners — chỉ banner đang thực sự hiển thị
    public function index()
    {
        $banners = Banner::query()->dangHienThi()->get();

        return BannerResource::collection($banners);
    }
}