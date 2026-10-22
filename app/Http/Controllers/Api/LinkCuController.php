<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
}
