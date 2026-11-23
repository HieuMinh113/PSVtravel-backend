<?php

namespace Tests\Feature;

use App\Filament\Pages\BangCongThang;
use App\Mcp\MaKhoaMcp;
use App\Models\Attendance;
use App\Models\ContactMessage;
use App\Models\User;
use App\Models\VoucherBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Payment;
use Modules\Maldives\Models\MaldivesBooking;
use Modules\Review\Models\Review;
use Modules\Tour\Models\Tour;
use Modules\Tour\Models\TourDeparture;
use Modules\Visa\Models\VisaCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * MCP nội bộ — nhóm công cụ XEM số liệu quản trị: đúng quyền của tài khoản,
 * số điện thoại / email khách che bớt, không lộ hộ chiếu, không sửa được gì.
 */
class McpQuanTriTest extends TestCase
{
    use RefreshDatabase;

    private const CONG_CU = ['attendance_month', 'get_booking', 'lookup_voucher', 'sales_report', 'search_bookings',
        'search_contact_messages', 'search_maldives_bookings', 'search_reviews', 'upcoming_departures', 'visa_cases'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-16 09:00'));
    }

    private function admin(array $quyen = []): User
    {
        $u = User::factory()->create();
        $u->assignRole(Role::findOrCreate('admin', 'web'));
        $u->givePermissionTo(collect($quyen)->map(fn ($q) => Permission::findOrCreate($q, 'web')));

        return $u;
    }

    private function congCu(string $ma, string $ten, array $thamSo = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/mcp/noi-bo', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
            'params' => ['name' => $ten, 'arguments' => $thamSo]],
            ['Accept' => 'application/json, text/event-stream', 'Authorization' => 'Bearer '.$ma]);
    }

    /** Gọi công cụ, chắc chắn không lỗi, trả structuredContent */
    private function ket(string $ma, string $ten, array $thamSo = []): array
    {
        $kq = $this->congCu($ma, $ten, $thamSo)->assertOk()->json('result');
        $this->assertNotTrue($kq['isError'] ?? false, json_encode($kq, JSON_UNESCAPED_UNICODE));

        return $kq['structuredContent'];
    }

    private function tour(): array
    {
        $tour = Tour::create(['slug' => 'seoul', 'name' => 'Seoul Nami 5N4Đ', 'type' => 'abroad', 'status' => 'published',
            'adult_price' => 10_000_000, 'duration_days' => 5]);

        return [$tour, TourDeparture::create(['tour_id' => $tour->id, 'start_date' => '2026-12-05', 'seats_total' => 20, 'seats_left' => 17])];
    }

    public function test_co_du_cong_cu_va_admin_chua_tick_quyen_thi_khong_xem_duoc(): void
    {
        $ma = MaKhoaMcp::tao($this->admin(), 'Laptop');

        $ten = collect($this->postJson('/mcp/noi-bo', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'],
            ['Accept' => 'application/json, text/event-stream', 'Authorization' => 'Bearer '.$ma])->json('result.tools'));
        foreach (self::CONG_CU as $c) {
            $this->assertTrue($ten->contains('name', $c), "thiếu công cụ $c");
            $this->assertTrue($ten->firstWhere('name', $c)['annotations']['readOnlyHint'] ?? false, "$c phải là chỉ đọc");
        }

        foreach (['sales_report' => [], 'search_bookings' => [], 'search_maldives_bookings' => [], 'upcoming_departures' => [],
            'attendance_month' => [], 'visa_cases' => [], 'search_reviews' => [], 'lookup_voucher' => ['ma' => 'PSV-AAAA-BBBB']] as $c => $thamSo) {
            $kq = $this->congCu($ma, $c, $thamSo)->json('result');
            $this->assertTrue($kq['isError'] ?? false, "$c phải báo chưa có quyền");
            $this->assertStringContainsString('chưa có quyền', $kq['content'][0]['text']);
        }
    }

    public function test_don_tour_che_so_dien_thoai_va_bao_cao_doanh_so_thang(): void
    {
        [$tour, $dot] = $this->tour();
        $sale = User::factory()->create(['name' => 'Thu Trang']);
        $don = Booking::create(['tour_id' => $tour->id, 'tour_departure_id' => $dot->id, 'customer_name' => 'Nguyễn Văn An',
            'customer_phone' => '0909 000 111', 'customer_email' => 'khachhang@gmail.com', 'adults' => 2, 'children' => 0,
            'unit_price_adult' => 10_000_000, 'total_price' => 20_000_000, 'status' => 'confirmed', 'payment_status' => 'unpaid',
            'deposit_percent' => 30, 'admin_note' => 'Khách cần phòng đôi']);
        $don->forceFill(['assigned_to' => $sale->id])->saveQuietly(); // người phụ trách không nằm trong fillable
        Payment::create(['booking_id' => $don->id, 'amount' => 5_000_000, 'method' => 'bank_transfer', 'status' => 'success', 'paid_at' => now()]);
        $huy = Booking::create(['tour_id' => $tour->id, 'tour_departure_id' => $dot->id, 'customer_name' => 'Trần Bình', 'customer_phone' => '0988777666',
            'adults' => 1, 'total_price' => 10_000_000, 'status' => 'confirmed', 'payment_status' => 'unpaid']);
        $huy->forceFill(['assigned_to' => $sale->id])->saveQuietly();
        $huy->update(['status' => 'cancelled', 'cancel_reason' => 'Khách bận']);
        Booking::create(['tour_id' => $tour->id, 'tour_departure_id' => $dot->id, 'customer_name' => 'Lê Cúc', 'customer_phone' => '0911222333',
            'adults' => 1, 'total_price' => 10_000_000, 'status' => 'pending', 'payment_status' => 'unpaid'])
            ->forceFill(['source' => 'web'])->saveQuietly();

        $ma = MaKhoaMcp::tao($this->admin(['ViewAny:Booking', 'View:Booking', 'ViewAll:Booking']), 'Laptop');

        // Tìm theo số điện thoại gõ liền → ra đúng đơn, số bị che
        $tim = $this->congCu($ma, 'search_bookings', ['tu_khoa' => '0909000111'])->assertOk();
        $this->assertSame(1, $tim->json('result.structuredContent.so_don_tim_thay'));
        $this->assertSame('090****111', $tim->json('result.structuredContent.don.0.sdt'));
        $this->assertSame(15_000_000, $tim->json('result.structuredContent.don.0.con_lai_vnd'));
        $this->assertStringNotContainsString('0909 000 111', $tim->getContent());

        $this->assertSame(1, $this->ket($ma, 'search_bookings', ['trang_thai' => 'pending'])['so_don_tim_thay']);
        $this->assertSame(3, $this->ket($ma, 'search_bookings', ['khoi_hanh_tu' => '2026-12-01', 'khoi_hanh_den' => '2026-12-31'])['so_don_tim_thay']);

        // Chi tiết: cọc 30% = 6 triệu, đã thu 5 triệu → đang cọc; email che
        $chiTiet = $this->congCu($ma, 'get_booking', ['ma_don' => strtolower($don->booking_code)])->assertOk();
        $ct = $chiTiet->json('result.structuredContent');
        $this->assertSame('k***@gmail.com', $ct['email']);
        $this->assertSame(6_000_000, $ct['coc']['so_tien_vnd']);
        $this->assertSame('Đang cọc', $ct['tinh_trang_tien']);
        $this->assertCount(1, $ct['khoan_thu']);
        $this->assertSame('Khách cần phòng đôi', $ct['ghi_chu_noi_bo']);
        $this->assertStringContainsString($don->booking_code, $ct['tin_nhan_nhac_dong_tien']);
        $this->assertStringNotContainsString('khachhang@gmail.com', $chiTiet->getContent());

        // Doanh số tháng 11: đơn huỷ không tính doanh số; theo người phụ trách
        $bc = $this->ket($ma, 'sales_report', ['thang' => '2026-11']);
        $this->assertSame('Mọi nhân viên', $bc['pham_vi']);
        $this->assertSame(1, $bc['tong_cong']['so_don']);
        $this->assertSame(20_000_000, $bc['tong_cong']['doanh_so']);
        $this->assertSame(5_000_000, $bc['tong_cong']['da_thu']);
        $this->assertSame(1, $bc['tong_cong']['huy']);
        $this->assertSame('Thu Trang', $bc['theo_nguoi_phu_trach'][0]['ten']);
        $this->assertSame(3, $bc['don_moi_tao_trong_thang']['tong']);
        $this->assertSame(1, $bc['don_moi_tao_trong_thang']['theo_nguon']['Khách đặt web']);
        $this->assertSame(0, $this->ket($ma, 'sales_report', ['thang' => '2026-10'])['tong_cong']['so_don']);
        $this->assertTrue($this->congCu($ma, 'sales_report', ['thang' => '11/2026'])->json('result.isError'));

        // Không có quyền xem của mọi người → chỉ đơn mình phụ trách
        $maSale = MaKhoaMcp::tao($this->admin(['ViewAny:Booking']), 'May sale');
        $rieng = $this->ket($maSale, 'sales_report', ['thang' => '2026-11']);
        $this->assertSame('Chỉ đơn bạn phụ trách', $rieng['pham_vi']);
        $this->assertSame(0, $rieng['tong_cong']['so_don']);
        $this->assertSame([], $rieng['theo_nguoi_phu_trach']);

        // Chỉ xem: không công cụ nào làm đổi đơn
        $this->assertSame('confirmed', $don->fresh()->status);
    }

    public function test_tin_lien_he_mac_dinh_tin_chua_xong_va_che_lien_lac(): void
    {
        ContactMessage::create(['name' => 'Chị Hoa', 'phone' => '0901234567', 'email' => 'hoa@yahoo.com', 'message' => 'Hỏi tour Tết', 'source' => 'contact', 'status' => 'new']);
        ContactMessage::create(['name' => 'Anh Nam', 'phone' => '0907654321', 'message' => 'Team building 50 người', 'source' => 'team_building', 'status' => 'handling']);
        ContactMessage::create(['name' => 'Cô Mai', 'phone' => '0903333444', 'message' => 'Đã gọi', 'source' => 'contact', 'status' => 'done']);
        $ma = MaKhoaMcp::tao($this->admin(), 'Laptop'); // vai trò Admin xử lý được tin liên hệ

        $r = $this->congCu($ma, 'search_contact_messages')->assertOk();
        $this->assertSame(2, $r->json('result.structuredContent.so_tin_tim_thay'));
        $this->assertSame('Anh Nam', $r->json('result.structuredContent.tin.0.ten'));
        $this->assertSame('h***@yahoo.com', $r->json('result.structuredContent.tin.1.email'));
        $this->assertStringNotContainsString('0901234567', $r->getContent());

        $this->assertSame(3, $this->ket($ma, 'search_contact_messages', ['trang_thai' => 'tat_ca'])['so_tin_tim_thay']);
        $this->assertSame(1, $this->ket($ma, 'search_contact_messages', ['nguon' => 'team_building'])['so_tin_tim_thay']);
    }

    public function test_dot_khoi_hanh_sap_toi_dem_khach_da_chot(): void
    {
        [$tour, $dot] = $this->tour();
        TourDeparture::create(['tour_id' => $tour->id, 'start_date' => '2027-03-01', 'seats_total' => 20, 'seats_left' => 20]);
        Booking::create(['tour_id' => $tour->id, 'tour_departure_id' => $dot->id, 'customer_name' => 'A', 'customer_phone' => '0900000001',
            'adults' => 2, 'children' => 1, 'total_price' => 25_000_000, 'status' => 'confirmed', 'payment_status' => 'unpaid']);
        Booking::create(['tour_id' => $tour->id, 'tour_departure_id' => $dot->id, 'customer_name' => 'B', 'customer_phone' => '0900000002',
            'adults' => 1, 'total_price' => 10_000_000, 'status' => 'pending', 'payment_status' => 'unpaid']);
        $ma = MaKhoaMcp::tao($this->admin(['ViewAny:Tour']), 'Laptop');

        $kq = $this->ket($ma, 'upcoming_departures', ['tu_khoa' => 'seoul']);
        $this->assertSame(1, $kq['so_dot']); // đợt tháng 3/2027 ngoài 60 ngày
        $this->assertSame(['tour' => 'Seoul Nami 5N4Đ', 'ngay_di' => '2026-12-05', 'con_cho' => 17, 'don_da_chot' => 1, 'khach_da_chot' => 3, 'don_cho_xu_ly' => 1],
            collect($kq['dot'][0])->only(['tour', 'ngay_di', 'con_cho', 'don_da_chot', 'khach_da_chot', 'don_cho_xu_ly'])->all());

        $this->assertSame(2, $this->ket($ma, 'upcoming_departures', ['so_ngay' => 120])['so_dot']);
        $this->assertSame(0, $this->ket($ma, 'upcoming_departures', ['tu_khoa' => 'da nang'])['so_dot']);
    }

    public function test_bang_cong_thang_chi_con_so_dung_nhu_trang_admin(): void
    {
        $vt = Role::findOrCreate('staff', 'web');
        $vt->givePermissionTo(Permission::findOrCreate('ViewAny:Booking', 'web'));
        $nv = User::factory()->create(['name' => 'Nhân viên Lan']);
        $nv->assignRole($vt);
        foreach (['2026-11-02' => '07:55', '2026-11-03' => '08:20'] as $ngay => $gio) {
            Attendance::create(['user_id' => $nv->id, 'work_date' => $ngay, 'in_at' => Carbon::parse("$ngay $gio"), 'in_source' => 'cham',
                'in_location' => 'trong', 'out_at' => Carbon::parse("$ngay 17:35"), 'out_source' => 'cham', 'out_location' => 'trong']);
        }
        $ma = MaKhoaMcp::tao($this->admin(['ViewAll:Attendance']), 'Laptop');

        $kq = $this->ket($ma, 'attendance_month', ['thang' => '2026-11', 'ten_nhan_vien' => 'lan']);
        $this->assertCount(1, $kq['nhan_vien']);
        // (so bằng assertEquals: JSON đổi 2.0 thành 2)
        $this->assertEquals(['ten' => 'Nhân viên Lan', ...BangCongThang::cong(Attendance::where('user_id', $nv->id)->get())], $kq['nhan_vien'][0]);
        $this->assertSame(1, $kq['nhan_vien'][0]['tre_lan']);
        $this->assertArrayNotHasKey('in_lat', $kq['nhan_vien'][0]);
    }

    public function test_ho_so_visa_khong_lo_ho_chieu_va_dung_pham_vi_nhan_vien(): void
    {
        $khac = User::factory()->create();
        VisaCase::create(['full_name' => 'KHACH CUA NGUOI KHAC', 'country' => 'Hàn Quốc', 'status' => 'dang_gom', 'assigned_to' => $khac->id]);
        VisaCase::create(['full_name' => 'NGUYEN VAN AN', 'country' => 'Hàn Quốc', 'status' => 'dang_gom', 'phone' => '0912345678',
            'passport_no' => 'C1234567', 'birth_date' => '1990-05-20', 'fee' => 2_000_000, 'paid' => 500_000,
            'checklist' => [['ten' => 'Hộ chiếu', 'trang_thai' => 'da_nhan'], ['ten' => 'Sao kê ngân hàng', 'trang_thai' => 'thieu']]]);
        VisaCase::create(['full_name' => 'KHACH DAU', 'country' => 'Nhật Bản', 'status' => 'dau']);

        // Admin chỉ có quyền xem (không ViewAll) → hồ sơ mình + hồ sơ chưa ai nhận
        $ma = MaKhoaMcp::tao($this->admin(['ViewAny:VisaCase']), 'Laptop');
        $r = $this->congCu($ma, 'visa_cases', ['quoc_gia' => 'han quoc'])->assertOk();
        $hs = $r->json('result.structuredContent');
        $this->assertSame(1, $hs['so_ho_so']);
        $this->assertSame('NGUYEN VAN AN', $hs['ho_so'][0]['khach']);
        $this->assertSame(['Sao kê ngân hàng'], $hs['ho_so'][0]['giay_con_thieu']);
        $this->assertSame('1/2', $hs['ho_so'][0]['giay_to']);
        $this->assertSame(1_500_000, $hs['ho_so'][0]['con_phai_thu_vnd']);
        $this->assertStringNotContainsString('C1234567', $r->getContent());
        $this->assertStringNotContainsString('1990-05-20', $r->getContent());
        $this->assertStringNotContainsString('0912345678', $r->getContent());

        // Mặc định chỉ hồ sơ đang xử lý; tat_ca thì có cả hồ sơ đã đậu
        $this->assertSame(2, $this->ket($ma, 'visa_cases', ['trang_thai' => 'tat_ca'])['so_ho_so']);
    }

    public function test_tra_voucher_danh_gia_va_don_maldives(): void
    {
        $dot = VoucherBatch::create(['name' => 'Ốc & Du lịch 200K', 'value' => 200000, 'expires_at' => now()->addMonth(),
            'applies_to' => 'Dịch vụ ăn uống', 'code_prefix' => 'psv', 'status' => 'active']);
        $dot->taoMa(1, 'Hội chợ 11/2026');
        $maVoucher = $dot->vouchers()->value('code');

        [$tour] = $this->tour();
        Review::create(['tour_id' => $tour->id, 'customer_name' => 'Chị Thu', 'rating' => 2, 'content' => 'Xe đón trễ', 'status' => 'pending']);
        Review::create(['tour_id' => $tour->id, 'customer_name' => 'Anh Hải', 'rating' => 5, 'content' => 'Rất tốt', 'status' => 'approved']);

        MaldivesBooking::create(['combo_name' => 'Maldives 4N3Đ', 'start_date' => '2026-12-20', 'adults' => 2, 'rooms' => 1,
            'total_usd' => 3000, 'customer_name' => 'Phạm Long', 'customer_phone' => '0912 345 678', 'status' => 'contacting']);

        $ma = MaKhoaMcp::tao($this->admin(['ViewAny:Voucher', 'ViewAny:Review', 'ViewAny:MaldivesBooking']), 'Laptop');

        $v = $this->ket($ma, 'lookup_voucher', ['ma' => strtolower(str_replace('-', ' ', $maVoucher))]);
        $this->assertSame(['ma' => $maVoucher, 'trang_thai' => 'Còn dùng được', 'menh_gia_vnd' => 200000, 'phat_cho' => 'Hội chợ 11/2026'],
            collect($v)->only(['ma', 'trang_thai', 'menh_gia_vnd', 'phat_cho'])->all());
        $this->assertTrue($this->congCu($ma, 'lookup_voucher', ['ma' => 'PSV-ZZZZ-ZZZZ'])->json('result.isError'));

        $dg = $this->ket($ma, 'search_reviews', ['sao_toi_da' => 3, 'tour' => 'nami']);
        $this->assertSame(1, $dg['so_danh_gia']);
        $this->assertSame(['khach' => 'Chị Thu', 'so_sao' => 2, 'trang_thai' => 'Chờ duyệt'], collect($dg['danh_gia'][0])->only(['khach', 'so_sao', 'trang_thai'])->all());

        $md = $this->ket($ma, 'search_maldives_bookings', ['tu_khoa' => '0912345678']);
        $this->assertSame(1, $md['so_don_tim_thay']);
        $this->assertSame(['khach' => 'Phạm Long', 'sdt' => '091****678', 'trang_thai' => 'Đang tư vấn'],
            collect($md['don'][0])->only(['khach', 'sdt', 'trang_thai'])->all());
        $this->assertSame(0, $this->ket($ma, 'search_maldives_bookings', ['trang_thai' => 'completed'])['so_don_tim_thay']);
    }
}
