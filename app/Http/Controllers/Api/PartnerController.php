<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Partner;

class PartnerController extends Controller
{
    // GET /api/v1/partners
    public function index()
    {
        $items = Partner::query()->dangHienThi()->limit(50)->get();

        return response()->json([
            'data' => $items->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'logo' => $this->anh($p->logo),
                'link_url' => $p->link_url,
            ])->values(),
        ]);
    }

    private function anh(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return str_starts_with($path, 'http') ? $path : asset('storage/'.$path);
    }
}
