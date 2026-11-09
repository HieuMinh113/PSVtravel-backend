<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NoiDungTeamBuilding;
use Illuminate\Http\JsonResponse;

/** GET /api/v1/team-building-page — nội dung trang giới thiệu Team Building (website). */
class TrangTeamBuildingController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => NoiDungTeamBuilding::congKhai()]);
    }
}
