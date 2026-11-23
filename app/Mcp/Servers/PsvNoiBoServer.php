<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\ChiTietTour;
use App\Mcp\Tools\NoiBo\BangCongThangMcp;
use App\Mcp\Tools\NoiBo\BaoCaoDoanhSo;
use App\Mcp\Tools\NoiBo\DotKhoiHanhSapToi;
use App\Mcp\Tools\NoiBo\HoSoVisaMcp;
use App\Mcp\Tools\NoiBo\LuaChonBai;
use App\Mcp\Tools\NoiBo\SuaBaiNhap;
use App\Mcp\Tools\NoiBo\TaoBaiNhap;
use App\Mcp\Tools\NoiBo\TaoLinkTaiAnh;
use App\Mcp\Tools\NoiBo\TimBaiNoiBo;
use App\Mcp\Tools\NoiBo\TimDanhGia;
use App\Mcp\Tools\NoiBo\TimDonMaldives;
use App\Mcp\Tools\NoiBo\TimDonTour;
use App\Mcp\Tools\NoiBo\TimTinLienHe;
use App\Mcp\Tools\NoiBo\TraVoucher;
use App\Mcp\Tools\NoiBo\XemBai;
use App\Mcp\Tools\NoiBo\XemDonTour;
use App\Mcp\Tools\TimTour;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * Máy chủ MCP NỘI BỘ — https://api.psvtravel.com/mcp/noi-bo
 *
 * Cho Admin dùng trợ lý AI trên máy mình (Claude Code, Claude Desktop…):
 *  - soạn bài cẩm nang: đọc tài liệu trong thư mục máy tính, tạo / sửa BÀI NHÁP,
 *    tải ảnh lên. Không có công cụ đăng bài: người duyệt bấm Đăng trong admin.
 *  - XEM số liệu quản trị (doanh số, đơn, tin liên hệ, đợt khởi hành, bảng công,
 *    hồ sơ visa, voucher, đánh giá) theo đúng quyền của tài khoản; số điện thoại
 *    / email khách che bớt, không trả hộ chiếu, ảnh, vị trí, tệp giấy tờ.
 *
 * Vào bằng mã khoá MCP (trang admin → Kết nối AI), chỉ vai trò Admin.
 */
#[Name('PSV Travel – Nội bộ')]
#[Version('1.1.0')]
#[Instructions(<<<'MD'
Máy chủ nội bộ của PSV Travel cho tài khoản Admin. Hai nhóm việc:

A. XEM SỐ LIỆU QUẢN TRỊ (chỉ đọc, không sửa được gì):
- sales_report: doanh số tour theo tháng chốt đơn, theo người phụ trách.
- search_bookings / get_booking: đơn tour; search_maldives_bookings: đơn Maldives.
- search_contact_messages: tin khách gửi chưa xử lý.
- upcoming_departures: đợt khởi hành sắp tới, chỗ còn, hạn nộp visa.
- attendance_month: bảng công tháng (chỉ con số).
- visa_cases: hồ sơ visa đang làm, giấy còn thiếu, lịch hẹn.
- lookup_voucher: tra mã voucher. search_reviews: đánh giá tour.
Số điện thoại / email khách đã che bớt — đừng đoán hay cố khôi phục; cần liên hệ khách thì đưa link_admin cho người dùng tự mở.
Mọi số tiền là VND trừ khi tên trường ghi _usd. Tool báo "chưa có quyền" thì nói người dùng nhờ quản trị tick quyền, đừng thử cách khác.
Muốn sửa đơn, duyệt, đổi trạng thái: hướng dẫn người dùng mở link_admin và làm trong admin.

B. SOẠN BÀI CẨM NANG cho website psvtravel.com. Quy trình:
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
    // Trả đủ mọi công cụ trong một lần liệt kê (mặc định 15/trang — có ứng
    // dụng AI không xin trang sau, sẽ không thấy các công cụ còn lại)
    public int $defaultPaginationLength = 50;

    protected array $tools = [
        // Xem số liệu quản trị (chỉ đọc)
        BaoCaoDoanhSo::class,
        TimDonTour::class,
        XemDonTour::class,
        TimDonMaldives::class,
        TimTinLienHe::class,
        DotKhoiHanhSapToi::class,
        BangCongThangMcp::class,
        HoSoVisaMcp::class,
        TraVoucher::class,
        TimDanhGia::class,
        // Soạn bài cẩm nang nháp
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
