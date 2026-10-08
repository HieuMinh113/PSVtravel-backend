<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Payment;
use Modules\Tour\Models\Tour;
use Modules\Tour\Models\TourDeparture;
use Tests\TestCase;

/**
 * Thông báo trực tiếp trên trang quản trị: "nhịp" 10 giây trả thông báo mới
 * (để phát tiếng + popup) và con số trên menu (cập nhật không cần F5); đơn khách
 * đặt trên web báo chuông cho người xử lý đơn.
 */
class ThongBaoTrucTiepTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function nguoi(string $vaiTro): User
    {
        return User::factory()->create()->assignRole($vaiTro);
    }

    public function test_don_web_moi_bao_chuong_cho_nguoi_xu_ly_don_va_nhip_tra_ve(): void
    {
        Mail::fake();
        $sale = $this->nguoi('sale');
        $keToan = $this->nguoi('ke_toan');
        $khach = $this->nguoi('customer');
        $tour = Tour::create(['slug' => 'seoul-'.uniqid(), 'name' => 'Seoul 5N4Đ', 'type' => 'abroad', 'status' => 'published', 'adult_price' => 10_000_000, 'duration_days' => 5]);
        $dot = TourDeparture::create(['tour_id' => $tour->id, 'start_date' => now()->addMonth()->toDateString(), 'seats_total' => 20, 'seats_left' => 20]);

        $this->postJson('/api/v1/bookings', [
            'tour_id' => $tour->id, 'tour_departure_id' => $dot->id,
            'customer_name' => 'Khách Web', 'customer_phone' => '0909123456', 'adults' => 2,
        ])->assertCreated();

        $this->assertSame(1, $sale->notifications()->count());
        $this->assertSame(0, $keToan->notifications()->count()); // kế toán không xử lý đơn
        $this->assertSame(0, $khach->notifications()->count());

        // Trang quản trị có gắn script nhịp + nút bật / tắt tiếng
        $this->actingAs($sale);
        $this->get('/admin/cham-cong')->assertOk()->assertSee('js/psv/thong-bao.js', false)->assertSee('psv-tieng', false);

        $nhip = $this->getJson('/admin/psv/nhip')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame(1, $nhip->json('chua_doc'));
        $this->assertStringContainsString('Đơn đặt tour mới từ website: Khách Web', $nhip->json('moi.0.title'));
        $this->assertStringContainsString('0909123456', $nhip->json('moi.0.body'));
        $this->assertStringContainsString('/admin/bookings/'.Booking::sole()->id, $nhip->json('moi.0.url'));
    }

    public function test_so_tren_menu_cap_nhat_qua_nhip(): void
    {
        $keToan = $this->nguoi('ke_toan');
        $this->actingAs($keToan);

        $khoanThu = fn () => collect($this->getJson('/admin/psv/nhip')->json('menu'))
            ->first(fn ($m) => str_ends_with($m['url'], '/admin/khoan-thu'));
        $this->assertNull($khoanThu()['html']);

        // Nhân viên ghi khoản thu chờ duyệt → số "1" ở Duyệt khoản thu
        $tour = Tour::create(['slug' => 'dn-'.uniqid(), 'name' => 'Đà Nẵng', 'type' => 'domestic', 'status' => 'published', 'adult_price' => 5_000_000, 'duration_days' => 3]);
        $don = Booking::create(['tour_id' => $tour->id, 'customer_name' => 'A', 'customer_phone' => '0909000111', 'adults' => 1, 'children' => 0,
            'unit_price_adult' => 5_000_000, 'total_price' => 5_000_000, 'status' => 'confirmed', 'payment_status' => 'unpaid']);
        Payment::create(['booking_id' => $don->id, 'amount' => 1_000_000, 'method' => 'cash', 'status' => 'pending', 'paid_at' => now(), 'received_by' => $keToan->id]);

        $this->assertStringContainsString('>1<', preg_replace('/\s+/', '', $khoanThu()['html']));
    }

    public function test_chua_dang_nhap_khong_hoi_duoc(): void
    {
        $this->get('/admin/psv/nhip')->assertRedirect();
    }
}
