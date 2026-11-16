<?php

namespace Tests\Feature;

use App\Mcp\Servers\PsvTravelServer;
use App\Mcp\Tools\ChiTietTour;
use App\Mcp\Tools\DichVuVisa;
use App\Mcp\Tools\GuiYeuCauTuVan;
use App\Mcp\Tools\ThongTinCongTy;
use App\Mcp\Tools\TimTour;
use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Tour\Models\Tour;
use Modules\Tour\Models\TourDeparture;
use Modules\Visa\Models\VisaCountry;
use Tests\TestCase;

/**
 * Máy chủ MCP /mcp cho trợ lý AI: chỉ dữ liệu công khai đang bán trên website,
 * và yêu cầu tư vấn chỉ gửi được khi khách đồng ý.
 */
class McpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.frontend_url' => 'https://psvtravel.com']);
        $this->travelTo(Carbon::parse('2026-11-16 09:00'));
    }

    private function tour(string $slug, array $them = []): Tour
    {
        return Tour::create(['slug' => $slug, 'name' => 'Tour '.$slug, 'type' => 'domestic', 'status' => 'published',
            'adult_price' => 3_000_000, 'duration_days' => 3, 'duration_nights' => 2, ...$them]);
    }

    private function dot(Tour $tour, string $ngay, array $them = []): TourDeparture
    {
        return TourDeparture::create(['tour_id' => $tour->id, 'start_date' => $ngay, 'seats_total' => 20, 'seats_left' => 12, ...$them]);
    }

    public function test_dia_chi_mcp_bat_tay_va_liet_ke_7_cong_cu(): void
    {
        $this->postJson('/mcp', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize',
            'params' => ['protocolVersion' => '2025-06-18', 'capabilities' => [], 'clientInfo' => ['name' => 'test', 'version' => '1']],
        ], ['Accept' => 'application/json, text/event-stream'])
            ->assertOk()
            ->assertJsonPath('result.serverInfo.name', 'PSV Travel');

        $ten = collect($this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'],
            ['Accept' => 'application/json, text/event-stream'])->json('result.tools'))->pluck('name')->sort()->values()->all();

        $this->assertSame(['company_info', 'get_tour', 'maldives_combos', 'search_guides', 'search_tours', 'submit_inquiry', 'visa_services'], $ten);
    }

    public function test_tim_tour_khong_dau_theo_thang_va_gia_chi_dot_con_dat_duoc(): void
    {
        $dn = $this->tour('da-nang', ['name' => 'Đà Nẵng - Hội An 3 ngày 2 đêm', 'region' => 'Miền Trung']);
        $this->dot($dn, '2026-11-10');                                  // đã đi
        $this->dot($dn, '2026-12-05', ['price_override' => 2_500_000]);
        $this->dot($dn, '2026-12-20', ['status' => 'closed']);          // đã đóng
        $this->dot($dn, '2027-01-08');

        $this->tour('da-lat', ['name' => 'Đà Lạt mộng mơ', 'adult_price' => 2_000_000]);
        $this->tour('nhap', ['name' => 'Đà Nẵng nháp', 'status' => 'draft']);
        $hq = $this->tour('han-quoc', ['name' => 'Hàn Quốc mùa thu', 'type' => 'abroad', 'country' => 'Hàn Quốc', 'adult_price' => 14_000_000]);
        $this->dot($hq, '2026-12-10');

        // "da nang" không dấu vẫn ra "Đà Nẵng"; tour nháp không bao giờ lộ ra
        PsvTravelServer::tool(TimTour::class, ['tu_khoa' => 'tour da nang'])
            ->assertOk()
            ->assertStructuredContentPath('so_tour_tim_thay', 1)
            ->assertStructuredContentPath('tours.0.slug', 'da-nang')
            ->assertStructuredContentPath('tours.0.gia_tu_vnd', 2_500_000)
            ->assertStructuredContentPath('tours.0.url', 'https://psvtravel.com/tour-trong-nuoc/da-nang')
            ->assertStructuredContentPath('tours.0.dot_gan_nhat', [
                ['ngay' => '2026-12-05', 'gia_vnd' => 2_500_000, 'con_cho' => 12],
                ['ngay' => '2027-01-08', 'gia_vnd' => 3_000_000, 'con_cho' => 12],
            ]);

        // Tháng 12 + dưới 10 triệu: chỉ Đà Nẵng (Hàn Quốc quá giá, Đà Lạt không có đợt tháng 12)
        PsvTravelServer::tool(TimTour::class, ['thang_khoi_hanh' => '2026-12', 'gia_toi_da' => 10_000_000])
            ->assertStructuredContentPath('so_tour_tim_thay', 1)
            ->assertStructuredContentPath('tours.0.dot_gan_nhat.0.ngay', '2026-12-05');

        PsvTravelServer::tool(TimTour::class, ['loai' => 'nuoc_ngoai'])
            ->assertStructuredContentPath('tours.0.url', 'https://psvtravel.com/tour-nuoc-ngoai/han-quoc');

        PsvTravelServer::tool(TimTour::class, ['thang_khoi_hanh' => '12/2026'])
            ->assertHasErrors(['thang_khoi_hanh phải có dạng YYYY-MM']);
    }

    public function test_chi_tiet_tour_co_lich_trinh_va_dot_con_cho(): void
    {
        $t = $this->tour('phu-quoc', ['name' => 'Phú Quốc 3N2Đ', 'included' => ['Vé máy bay', 'Khách sạn 4 sao'],
            'cancellation_policy' => '<p>Huỷ trước <b>7 ngày</b> hoàn 80%.</p>']);
        $t->itineraries()->create(['day_number' => 1, 'title' => 'Bãi Sao', 'description' => '<p>Tắm biển &amp; ăn hải sản</p>']);
        $this->dot($t, '2026-12-01');
        $this->dot($t, '2026-12-08', ['seats_left' => 0]); // hết chỗ

        PsvTravelServer::tool(ChiTietTour::class, ['slug' => 'phu-quoc'])
            ->assertOk()
            ->assertStructuredContentPath('ten', 'Phú Quốc 3N2Đ')
            ->assertStructuredContentPath('lich_trinh.0.noi_dung', 'Tắm biển & ăn hải sản')
            ->assertStructuredContentPath('bao_gom', ['Vé máy bay', 'Khách sạn 4 sao'])
            ->assertStructuredContentPath('chinh_sach_huy', 'Huỷ trước 7 ngày hoàn 80%.')
            ->assertStructuredContentCount(1, 'dot_khoi_hanh');

        PsvTravelServer::tool(ChiTietTour::class, ['slug' => 'khong-co'])
            ->assertHasErrors(['Không tìm thấy tour']);
    }

    public function test_dich_vu_visa_chi_trang_dang_hien(): void
    {
        VisaCountry::create(['name' => 'Nhật Bản', 'slug' => 'nhat-ban', 'visa_type' => 'tourist', 'price' => 1_900_000,
            'required_documents' => ['Hộ chiếu', 'Sao kê ngân hàng'], 'status' => 'published']);
        VisaCountry::create(['name' => 'Úc', 'slug' => 'uc', 'visa_type' => 'tourist', 'price' => 3_000_000, 'status' => 'draft']);

        PsvTravelServer::tool(DichVuVisa::class, ['quoc_gia' => 'nhat'])
            ->assertStructuredContentPath('so_dich_vu', 1)
            ->assertStructuredContentPath('dich_vu.0.giay_to_can', ['Hộ chiếu', 'Sao kê ngân hàng'])
            ->assertStructuredContentPath('dich_vu.0.url', 'https://psvtravel.com/lam-visa/nhat-ban');

        PsvTravelServer::tool(DichVuVisa::class, ['quoc_gia' => 'Úc'])
            ->assertStructuredContentPath('so_dich_vu', 0);
    }

    public function test_thong_tin_cong_ty_dung_thong_tin_dang_cong_khai(): void
    {
        PsvTravelServer::tool(ThongTinCongTy::class)
            ->assertOk()
            ->assertStructuredContentPath('giay_phep_lu_hanh_quoc_te', '79-769/2020/CDLQGVN-GP LHQT')
            ->assertStructuredContentPath('website', 'https://psvtravel.com');
    }

    public function test_gui_yeu_cau_can_khach_dong_y_va_chong_spam(): void
    {
        $this->tour('da-nang', ['name' => 'Đà Nẵng - Hội An']);
        $yeuCau = ['ho_ten' => 'Nguyễn Văn A', 'so_dien_thoai' => '0901 234 567', 'noi_dung' => 'Muốn đi Đà Nẵng cuối tháng 12',
            'tour_slug' => 'da-nang', 'so_nguoi' => '2 người lớn'];

        // Chưa có đồng ý của khách → không lưu gì
        PsvTravelServer::tool(GuiYeuCauTuVan::class, $yeuCau)->assertHasErrors(['Cần khách đồng ý']);
        PsvTravelServer::tool(GuiYeuCauTuVan::class, [...$yeuCau, 'khach_dong_y' => false])->assertHasErrors(['Cần khách đồng ý']);
        $this->assertSame(0, ContactMessage::count());

        PsvTravelServer::tool(GuiYeuCauTuVan::class, [...$yeuCau, 'khach_dong_y' => true])
            ->assertOk()
            ->assertStructuredContentPath('da_gui', true);

        $tin = ContactMessage::sole();
        $this->assertSame('tro_ly_ai', $tin->source);
        $this->assertSame('tro_ly_ai_mcp', $tin->kenh);
        $this->assertSame('Trợ lý AI: tư vấn Đà Nẵng - Hội An', $tin->subject);
        $this->assertStringContainsString('Số người: 2 người lớn', $tin->message);
        $this->assertSame('https://psvtravel.com/tour-trong-nuoc/da-nang', $tin->meta['tour_url']);

        // Gọi lại y hệt (trợ lý AI thử lại) → không tạo trùng
        PsvTravelServer::tool(GuiYeuCauTuVan::class, [...$yeuCau, 'khach_dong_y' => true])->assertOk();
        $this->assertSame(1, ContactMessage::count());

        // Cùng số điện thoại: tối đa 3 yêu cầu / ngày
        foreach (['Hỏi thêm về Phú Quốc tháng 1', 'Hỏi thêm về Sa Pa tháng 2'] as $nd) {
            PsvTravelServer::tool(GuiYeuCauTuVan::class, [...$yeuCau, 'noi_dung' => $nd, 'khach_dong_y' => true])->assertOk();
        }
        PsvTravelServer::tool(GuiYeuCauTuVan::class, [...$yeuCau, 'noi_dung' => 'Hỏi thêm về Hà Giang', 'khach_dong_y' => true])
            ->assertHasErrors(['đã gửi nhiều yêu cầu']);
        $this->assertSame(3, ContactMessage::count());
    }
}
