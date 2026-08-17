<?php

namespace Modules\Visa\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Visa\Models\VisaCountry;
use Modules\Visa\Transformers\VisaCountryResource;

class VisaApiController extends Controller
{
    // GET /api/v1/visa-countries?type=tourist
    public function index(Request $request)
    {
        $query = VisaCountry::query()->dangHienThi();

        if (in_array($request->query('type'), ['tourist', 'business', 'study'], true)) {
            $query->where('visa_type', $request->query('type'));
        }

        return VisaCountryResource::collection($query->get());
    }

    // GET /api/v1/visa-countries/{slug}
    public function show(string $slug)
    {
        $country = VisaCountry::query()
            ->dangHienThi()
            ->where('slug', $slug)
            ->firstOrFail();

        return new VisaCountryResource($country);
    }
}