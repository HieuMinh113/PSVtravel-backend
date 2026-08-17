<?php

namespace Modules\Page\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\Page\Models\Page;
use Modules\Page\Transformers\PageResource;

class PageApiController extends Controller
{
    // GET /api/v1/pages/{slug}
    public function show(string $slug)
    {
        $page = Page::query()
            ->where('status', 'published')
            ->where('slug', $slug)
            ->firstOrFail();

        return new PageResource($page);
    }
}