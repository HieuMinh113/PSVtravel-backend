<?php

namespace Tests\Feature;

use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Bookings\Pages\CreateBooking;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Resources\Bookings\Pages\ViewBooking;
use App\Mail\BookingMail;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Payment;
use Modules\Tour\Models\Tour;
use Modules\Tour\Models\TourDeparture;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Đơn đặt tour: tỷ lệ cọc 10–100%, hạn nhắn khách đóng phần còn lại (ngày đi
 * − 7), chuông cho người tạo đơn, tin nhắn nhắc đóng tiền, phiếu xác nhận PDF.
 */
class DonTourNhacKhachTest extends TestCase
{
    use RefreshDatabase;

    private Tour $tour;

    private TourDeparture $dot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-07 09:00'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->tour = Tour::create(['slug' => 'seoul-'.uniqid(), 'name' => 'Seoul Nami 5N4Đ', 'type' => 'abroad', 'status' => 'published', 'adult_price' => 10_000_000, 'duration_days' => 5]);
        $this->dot = TourDeparture::create(['tour_id' => $this->tour->id, 'start_date' => '2026-10-20', 'seats_total' => 20, 'seats_left' => 20]);
    }

    private function nhanVien(): User
    {
        $u = User::factory()->create();
        $u->givePermissionTo(collect(['ViewAny:Booking', 'View:Booking', 'Create:Booking', 'Update:Booking'])
            ->map(fn ($q) => Permission::findOrCreate($q, 'web')));

        return $u;
    }

    private function don(array $them = []): Booking
    {
        return Booking::create([
            'tour_id' => $this->tour->id, 'tour_departure_id' => $this->dot->id,
            'customer_name' => 'Nguyễn Văn A', 'customer_phone' => '0909000111', 'customer_email' => 'a@example.com',
            'adults' => 2, 'children' => 0, 'unit_price_adult' => 10_000_000, 'total_price' => 20_000_000,
            'status' => 'confirmed', 'payment_status' => 'unpaid',
            ...$them,
        ]);
    }

    public function test_tinh_tien_coc_va_han_nhan_khach(): void
    {
        $don = $this->don(['deposit_percent' => 30]);
        $this->assertSame(6_000_000, $don->deposit_amount);
        $this->assertSame('2026-10-13', $don->remind_on->toDateString()); // 20/10 − 7

        // Làm tròn nghìn đồng, nhận số lẻ
        $don->update(['deposit_percent' => 33.3, 'total_price' => 1_234_567]);
        $this->assertSame(411_000, $don->fresh()->deposit_amount);
        $this->assertSame('33,3%', Booking::phanTram($don->fresh()->deposit_percent));

        // Bỏ cọc
        $don->update(['deposit_percent' => null]);
        $this->assertNull($don->fresh()->deposit_amount);

        // Nhân viên tự sửa hạn → giữ ngày đó; đổi đợt khởi hành → tính lại
        $don->update(['remind_on' => '2026-10-10']);
        $this->assertSame('2026-10-10', $don->fresh()->remind_on->toDateString());
        $dotMoi = TourDeparture::create(['tour_id' => $this->tour->id, 'start_date' => '2026-11-15', 'seats_total' => 20, 'seats_left' => 20]);
        $don->update(['tour_departure_id' => $dotMoi->id]);
        $this->assertSame('2026-11-08', $don->fresh()->remind_on->toDateString());
    }

    public function test_tao_don_trong_quan_tri_ghi_nguoi_tao_va_kiem_tra_ty_le(): void
    {
        $nv = $this->nhanVien();
        $this->actingAs($nv);

        $form = [
            'tour_id' => $this->tour->id, 'tour_departure_id' => $this->dot->id,
            'customer_name' => 'Trần Thị B', 'customer_phone' => '0912000111',
            'adults' => 1, 'children' => 0, 'unit_price_adult' => 10_000_000,
        ];

        Livewire::test(CreateBooking::class)
            ->fillForm([...$form, 'deposit_percent' => 5])->call('create')
            ->assertHasFormErrors(['deposit_percent']);
        Livewire::test(CreateBooking::class)
            ->fillForm([...$form, 'deposit_percent' => 150])->call('create')
            ->assertHasFormErrors(['deposit_percent']);

        Livewire::test(CreateBooking::class)
            ->fillForm($form)
            ->assertFormSet(['remind_on' => '2026-10-13'])
            ->fillForm(['deposit_percent' => 50])
            ->call('create')
            ->assertHasNoFormErrors();

        $don = Booking::where('customer_name', 'Trần Thị B')->firstOrFail();
        $this->assertSame($nv->id, $don->created_by);
        $this->assertSame(5_000_000, $don->deposit_amount);
        $this->assertSame('2026-10-13', $don->remind_on->toDateString());
    }

    public function test_chuong_bao_nguoi_tao_khi_toi_han_mot_lan(): void
    {
        $a = $this->nhanVien();
        $b = $this->nhanVien();
        $this->actingAs($a);
        $toiHan = $this->don(['remind_on' => '2026-10-07']);
        $chuaToi = $this->don(['remind_on' => '2026-10-12']);
        $daTraDu = $this->don(['remind_on' => '2026-10-05', 'payment_status' => 'paid']);
        $daHuy = $this->don(['remind_on' => '2026-10-05', 'status' => 'cancelled']);
        auth()->logout();
        $webChuaAiNhan = $this->don(['remind_on' => '2026-10-06', 'status' => 'pending']);
        $this->assertNull($webChuaAiNhan->created_by);

        $this->artisan('don-tour:nhac-nhan-khach')->assertSuccessful();

        $this->assertSame(2, $a->notifications()->count()); // đơn của mình + đơn web chưa ai nhận
        $this->assertSame(1, $b->notifications()->count()); // chỉ đơn web chưa ai nhận
        $this->assertStringContainsString('Nguyễn Văn A', $a->notifications()->first()->data['title']);
        $this->assertNotNull($toiHan->fresh()->remind_notified_at);
        $this->assertNull($chuaToi->fresh()->remind_notified_at);
        $this->assertNull($daTraDu->fresh()->remind_notified_at);
        $this->assertNull($daHuy->fresh()->remind_notified_at);

        // Chạy lại không báo trùng
        $this->artisan('don-tour:nhac-nhan-khach')->assertSuccessful();
        $this->assertSame(2, $a->notifications()->count());

        // Tới ngày của đơn kia thì báo tiếp
        $this->travelTo(Carbon::parse('2026-10-12 08:00'));
        $this->artisan('don-tour:nhac-nhan-khach')->assertSuccessful();
        $this->assertSame(3, $a->notifications()->count());

        // Huy hiệu menu = đơn của mình tới hạn chưa nhắn
        $this->actingAs($a);
        $this->assertSame('2', BookingResource::getNavigationBadge());
    }

    public function test_tin_nhan_nhac_dong_tien_va_danh_dau_da_nhan(): void
    {
        DB::table('settings')->where('key', 'bank_transfer')->update(['value' => "Vietcombank\nSTK 0123456789\nCông ty PSV"]);
        $nv = $this->nhanVien();
        $this->actingAs($nv);
        $don = $this->don(['deposit_percent' => 30, 'remind_on' => '2026-10-07']);
        Payment::create(['booking_id' => $don->id, 'amount' => 2_000_000, 'method' => 'bank_transfer', 'status' => 'success', 'paid_at' => now()]);

        $tin = $don->fresh()->tinNhanNhacTien("Vietcombank\nSTK 0123456789");
        $this->assertStringContainsString('Tổng tiền: 20.000.000đ', $tin);
        $this->assertStringContainsString('Đã thanh toán: 2.000.000đ', $tin);
        $this->assertStringContainsString('Tiền cọc (30%): 6.000.000đ — còn thiếu 4.000.000đ', $tin);
        $this->assertStringContainsString('Còn lại cần thanh toán: 18.000.000đ', $tin);
        $this->assertStringContainsString('trước ngày 07/10/2026', $tin);
        $this->assertStringContainsString('Nội dung chuyển khoản: '.$don->booking_code, $tin);

        Livewire::test(ListBookings::class)
            ->filterTable('can_nhac', true)
            ->assertCanSeeTableRecords([$don])
            ->assertSee('Chưa đủ cọc 30%');

        Livewire::test(ViewBooking::class, ['record' => $don->getRouteKey()])
            ->mountAction('nhacDongTien')
            ->assertSet('mountedActions.0.data.tin', fn ($v) => str_contains($v, 'STK 0123456789'))
            ->callMountedAction();

        $don->refresh();
        $this->assertNotNull($don->reminded_at);
        $this->assertSame($nv->id, $don->reminded_by);
        $this->assertFalse($don->canNhacKhach());
        Livewire::test(ListBookings::class)->filterTable('can_nhac', true)->assertCanNotSeeTableRecords([$don]);
    }

    public function test_xac_nhan_don_web_thi_nguoi_xac_nhan_phu_trach(): void
    {
        $don = $this->don(['status' => 'pending']);
        $this->assertNull($don->created_by);
        $nv = $this->nhanVien();
        $this->actingAs($nv);

        Livewire::test(ListBookings::class)->callTableAction('confirm', $don);
        $this->assertSame($nv->id, $don->fresh()->created_by);
        $this->assertSame('confirmed', $don->fresh()->status);
    }

    public function test_phieu_xac_nhan_pdf_va_email_co_tien_coc(): void
    {
        $don = $this->don(['deposit_percent' => 30]);

        $this->get(route('booking.phieu-xac-nhan', $don))->assertForbidden();
        $this->actingAs(User::factory()->create());
        $this->get(route('booking.phieu-xac-nhan', $don))->assertForbidden();

        $this->actingAs($this->nhanVien());
        $tl = $this->get(route('booking.phieu-xac-nhan', $don))->assertOk();
        $this->assertSame('application/pdf', $tl->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $tl->getContent());
        $this->assertStringContainsString('Phieu-xac-nhan-'.$don->booking_code.'.pdf', $tl->headers->get('content-disposition'));

        $html = (new BookingMail($don->load(['tour', 'departure']), BookingMail::XAC_NHAN))->render();
        $this->assertStringContainsString('Tiền cọc (30%)', $html);
        $this->assertStringContainsString('6.000.000đ', $html);
        $this->assertStringContainsString('Trước ngày 13/10/2026', $html);
    }
}
