<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\ChiTietTour;
use App\Mcp\Tools\ComboMaldives;
use App\Mcp\Tools\DichVuVisa;
use App\Mcp\Tools\GuiYeuCauTuVan;
use App\Mcp\Tools\ThongTinCongTy;
use App\Mcp\Tools\TimCamNang;
use App\Mcp\Tools\TimTour;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * Máy chủ MCP công khai của PSV Travel — https://api.psvtravel.com/mcp
 *
 * Cho trợ lý AI (Claude, ChatGPT, Cursor…) tra dữ liệu THẬT đang bán trên website:
 * tour, lịch khởi hành, giá, chỗ còn, visa, combo Maldives, cẩm nang, thông tin
 * công ty; và gửi yêu cầu tư vấn khi khách đồng ý. Chỉ dữ liệu công khai — không
 * có đơn hàng, hồ sơ visa, CV, chấm công hay tên đối tác.
 */
#[Name('PSV Travel')]
#[Version('1.0.0')]
#[Instructions(<<<'MD'
PSV Travel (psvtravel.com) là công ty lữ hành quốc tế tại TP.HCM: tour du lịch trọn gói trong nước và nước ngoài, dịch vụ làm visa, combo nghỉ dưỡng Maldives.

- Dùng search_tours để tìm tour theo điểm đến / tháng / ngân sách, rồi get_tour để xem lịch trình và các đợt khởi hành.
- Giá tour tính bằng VND cho một người lớn; combo Maldives tính bằng USD. Số chỗ thay đổi liên tục, nhân viên xác nhận khi đặt — đừng hứa chắc còn chỗ.
- Luôn kèm link trang (url) để khách xem chi tiết và đặt trên website.
- Khách muốn đặt hoặc cần tư vấn: hỏi khách có đồng ý để lại họ tên + số điện thoại cho PSV Travel gọi lại không; chỉ khi khách đồng ý mới gọi submit_inquiry với khach_dong_y = true.
- Trả lời bằng ngôn ngữ của khách (mặc định tiếng Việt).

PSV Travel is a Vietnamese tour operator. Prices are real-time data from its booking system.
MD)]
class PsvTravelServer extends Server
{
    protected array $tools = [
        TimTour::class,
        ChiTietTour::class,
        DichVuVisa::class,
        ComboMaldives::class,
        TimCamNang::class,
        ThongTinCongTy::class,
        GuiYeuCauTuVan::class,
    ];
}
