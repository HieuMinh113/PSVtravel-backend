<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DoiDuongDan;
use App\Services\LinkCu;
use Illuminate\Http\Request;

class LinkCuController extends Controller
{
    // GET /api/v1/link-cu?path=/tour_nuoc_ngoai/abc — website gọi khi gặp đường
    // dẫn của web cũ: {ma: 301, toi: "/tour-nuoc-ngoai/xyz"} | {ma: 410} | {ma: 404}
    public function __invoke(Request $request)
    {
        return response()->json(LinkCu::tra((string) $request->query('path', '')));
    }

    // GET /api/v1/link-cu/doi-duong-dan — tour / bài đã đổi đường dẫn: {data: {cũ: mới}}.
    // Website giữ trong bộ nhớ 60 giây, chuyển hướng ngay ở proxy.
    public function doiDuongDan()
    {
        return response()->json(['data' => (object) DoiDuongDan::danhSach()]);
    }
}
