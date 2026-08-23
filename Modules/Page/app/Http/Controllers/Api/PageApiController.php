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
        // Không lọc theo status ở đây.
        //
        // Các trang chính sách bắt buộc phải tồn tại trên website ngay cả khi
        // bộ phận pháp chế chưa soạn xong nội dung — cơ quan quản lý vào kiểm
        // tra mà gặp 404 là trượt hồ sơ. Trang chưa đăng thì vẫn trả về, chỉ là
        // phần nội dung rỗng, và frontend hiện dòng "đang cập nhật".
        $page = Page::query()->where('slug', $slug)->firstOrFail();

        return new PageResource($page);
    }
}