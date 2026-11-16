<?php

use App\Http\Controllers\Mcp\TaiAnhBaiController;
use App\Http\Middleware\ChiMaKhoaMcpAdmin;
use App\Mcp\Servers\PsvNoiBoServer;
use App\Mcp\Servers\PsvTravelServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

// Máy chủ MCP công khai cho trợ lý AI: https://api.psvtravel.com/mcp
// (gói laravel/mcp tự nạp file này). Giới hạn tần suất riêng "mcp".
Mcp::web('/mcp', PsvTravelServer::class)->middleware('throttle:mcp');

// Máy chủ MCP nội bộ soạn bài cẩm nang nháp: mã khoá MCP của tài khoản Admin
// (tạo ở trang admin → Kết nối AI). Mã đăng nhập website không vào được.
Mcp::web('/mcp/noi-bo', PsvNoiBoServer::class)
    ->middleware([ChiMaKhoaMcpAdmin::class, 'throttle:mcp-noi-bo']);

// Nhận tệp ảnh từ máy nhân viên — link có chữ ký, dùng một lần (request_image_upload)
Route::post('/mcp/noi-bo/tai-anh', TaiAnhBaiController::class)
    ->middleware(['signed', 'throttle:mcp-noi-bo'])
    ->name('mcp.noi-bo.tai-anh');
