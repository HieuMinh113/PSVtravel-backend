<?php

namespace Modules\Flight\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\Flight\Models\Airline;
use Modules\Flight\Models\FlightDeal;
use Modules\Flight\Transformers\AirlineResource;
use Modules\Flight\Transformers\FlightDealResource;

class FlightApiController extends Controller
{
    // GET /api/v1/airlines
    public function airlines()
    {
        $airlines = Airline::query()->dangHienThi()->get();

        return AirlineResource::collection($airlines);
    }

    // GET /api/v1/flight-deals — chỉ chặng còn hiệu lực
    public function deals()
    {
        $deals = FlightDeal::query()
            ->dangHienThi()
            ->with('airline:id,code,name,logo')
            ->orderBy('sort_order')
            ->get();

        return FlightDealResource::collection($deals);
    }
}