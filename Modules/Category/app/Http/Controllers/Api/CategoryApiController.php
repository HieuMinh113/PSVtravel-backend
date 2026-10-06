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
            // Chỉ đếm tour ĐANG HIỂN THỊ, đúng bằng số tour khách thật sự thấy.
            //
            // Trước đây đếm cả tour còn ở trạng thái nháp/ẩn, nên mega menu ghi
            // "Miền Trung 2 tour" mà bấm vào trang danh sách lại ra "0 tour phù
            // hợp" — con số một đằng, nội dung một nẻo.
            ->withCount([
                'tours' => fn ($q) => $q->whereNull('tours.deleted_at')
                    ->where('tours.status', 'published'),
            ]);

        if (in_array($request->query('type'), ['domestic', 'abroad'], true)) {
            $query->where('type', $request->query('type'));
        }

        return CategoryResource::collection($query->get());
    }
}