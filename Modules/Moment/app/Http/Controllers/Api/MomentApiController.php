<?php

namespace Modules\Moment\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\Moment\Models\Moment;
use Modules\Moment\Transformers\MomentResource;

class MomentApiController extends Controller
{
    // GET /api/v1/moments — tối đa 30 ảnh mới nhất
    public function index()
    {
        $moments = Moment::query()
            ->dangHienThi()
            ->with('tour:id,name')
            ->latest('id')
            ->limit(30)
            ->get();

        return MomentResource::collection($moments);
    }
}