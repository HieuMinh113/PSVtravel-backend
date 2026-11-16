<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\ChiTietTour;
use App\Mcp\Tools\NoiBo\LuaChonBai;
use App\Mcp\Tools\NoiBo\SuaBaiNhap;
use App\Mcp\Tools\NoiBo\TaoBaiNhap;
use App\Mcp\Tools\NoiBo\TaoLinkTaiAnh;
use App\Mcp\Tools\NoiBo\TimBaiNoiBo;
use App\Mcp\Tools\NoiBo\XemBai;
use App\Mcp\Tools\TimTour;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * Máy chủ MCP NỘI BỘ — https://api.psvtravel.com/mcp/noi-bo
 *
 * Cho Admin dùng trợ lý AI trên máy mình (Claude Code, Claude Desktop…) soạn bài
 * cẩm nang: đọc tài liệu trong thư mục máy tính, tạo / sửa BÀI NHÁP, tải ảnh lên.
 * Không có công cụ đăng bài: người duyệt bấm Đăng trong admin.
 *
 * Vào bằng mã khoá MCP (trang admin → Kết nối AI), chỉ vai trò Admin.
 */
#[Name('PSV Travel – Nội bộ')]
#[Version('1.0.0')]
#[Instructions(<<<'MD'
Máy chủ nội bộ để soạn bài cẩm nang cho website psvtravel.com.

Quy trình:
1. guide_options: xem mã chuyên mục, tác giả và hướng dẫn viết.
2. search_guides_admin: kiểm tra chủ đề đã có bài chưa; lấy link bài đã đăng để chèn link nội bộ.
3. search_tours / get_tour: lấy giá, lịch trình, link tour THẬT — không tự đoán giá hay lịch khởi hành.
4. create_guide_draft: tạo bài nháp (Markdown, mục bằng ## / ###).
5. Ảnh: request_image_upload → gửi tệp ảnh trên máy bằng lệnh curl trong cach_tai → ảnh bìa tự gắn; ảnh trong bài thì chèn markdown trả về rồi update_guide_draft.
6. Báo người dùng mở link_sua_trong_admin để duyệt và bấm Đăng. Bạn KHÔNG đăng bài được.

Chỉ đọc các thư mục người dùng chỉ định; không mở thư mục chứa hồ sơ, giấy tờ hay dữ liệu cá nhân của khách.
MD)]
class PsvNoiBoServer extends Server
{
    protected array $tools = [
        LuaChonBai::class,
        TimBaiNoiBo::class,
        XemBai::class,
        TaoBaiNhap::class,
        SuaBaiNhap::class,
        TaoLinkTaiAnh::class,
        TimTour::class,
        ChiTietTour::class,
    ];
}
