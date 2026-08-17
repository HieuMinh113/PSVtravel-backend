<?php

namespace Modules\Category\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Category\Models\Category;
use Modules\Category\Transformers\CategoryResource;

class CategoryApiController extends Controller
{
    // GET /api/v1/categories?type=domestic
    public function index(Request $request)
    {
        $query = Category::query()
            ->dangHienThi()
            ->withCount(['tours' => fn ($q) => $q->whereNull('tours.deleted_at')]);

        if (in_array($request->query('type'), ['domestic', 'abroad'], true)) {
            $query->where('type', $request->query('type'));
        }

        return CategoryResource::collection($query->get());
    }
}