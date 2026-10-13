<?php

namespace Tests\Feature;

use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Tours\Pages\EditTour;
use App\Filament\Resources\Tours\RelationManagers\DeparturesRelationManager;
use App\Mail\BookingMail;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Modules\Booking\Models\Booking;
use Modules\Tour\Models\Tour;
use Modules\Tour\Models\TourDeparture;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Hạn nộp hồ sơ visa của tour nước ngoài: quy tắc chung "trước ngày khởi hành
 * N ngày" ở tour + hạn riêng từng ngày khởi hành. Khách thấy trên trang tour,
 * email, phiếu xác nhận, trang tài khoản; nhân viên thấy trong admin.
 */
class HanNopVisaTest extends TestCase
{
    use RefreshDatabase;

    private Tour $tour;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-13 09:00'));
        $this->tour = Tour::create(['slug' => 'seoul-nami', 'name' => 'Seoul Nami 5N4Đ', 'type' => 'abroad', 'status' => 'published',
            'adult_price' => 10_000_000, 'duration_days' => 5, 'duration_nights' => 4, 'visa_deadline_days' => 30]);
    }

    private function dot(string $ngay, array $them = []): TourDeparture
    {
        return TourDeparture::create(['tour_id' => $this->tour->id, 'start_date' => $ngay, 'seats_total' => 20, 'seats_left' => 20, ...$them]);
    }

    public function test_han_theo_quy_tac_chung_hoac_han_rieng_cua_dot(): void
    {
        $this->assertSame('2026-11-15', $this->dot('2026-12-15')->hanVisa()->toDateString()); // 15/12 − 30 ngày
        $this->assertSame('2026-11-20', $this->dot('2026-12-20', ['visa_deadline' => '2026-11-20'])->hanVisa()->toDateString());

        $this->tour->update(['visa_deadline_days' => null]);
        $this->assertNull($this->dot('2026-12-25')->fresh()->hanVisa());
        $this->assertSame(-3, TourDeparture::conNgay(Carbon::parse('2026-10-10')));
        $this->assertSame(7, TourDeparture::conNgay(Carbon::parse('2026-10-20')));
    }

    public function test_api_trang_tour_tra_han_visa_tung_dot(): void
    {
        $this->dot('2026-12-15');
        $this->dot('2026-12-20', ['visa_deadline' => '2026-11-20']);

        $r = $this->getJson('/api/v1/tours/seoul-nami')->assertOk();
        $this->assertSame(30, $r->json('data.visa_deadline_days'));
        $this->assertSame(['2026-11-15', '2026-11-20'], array_column($r->json('data.departures'), 'visa_deadline'));
        $this->assertSame('15/11/2026', $r->json('data.departures.0.visa_deadline_display'));
    }

    public function test_admin_dat_quy_tac_o_tour_va_han_rieng_o_lich_khoi_hanh(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $nv = User::factory()->create();
        foreach (['View', 'ViewAny', 'Update', 'Create'] as $q) {
            $nv->givePermissionTo(Permission::findOrCreate("{$q}:Tour", 'web'), Permission::findOrCreate("{$q}:TourDeparture", 'web'));
        }
        $this->actingAs($nv);

        Livewire::test(EditTour::class, ['record' => $this->tour->getRouteKey()])
            ->assertFormFieldExists('visa_deadline_days')
            ->fillForm(['visa_deadline_days' => 45])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(45, $this->tour->fresh()->visa_deadline_days);

        $rm = fn () => Livewire::test(DeparturesRelationManager::class, ['ownerRecord' => $this->tour->fresh(), 'pageClass' => EditTour::class]);
        $rm()->callAction(TestAction::make('create')->table(), data: [
            'start_date' => '2026-12-15', 'visa_deadline' => '2026-12-20', 'seats_total' => 20, 'seats_left' => 20, 'status' => 'open',
        ])->assertHasFormErrors(['visa_deadline' => 'before']);
        $rm()->callAction(TestAction::make('create')->table(), data: [
            'start_date' => '2026-12-15', 'visa_deadline' => '2026-11-25', 'seats_total' => 20, 'seats_left' => 20, 'status' => 'open',
        ])->assertHasNoFormErrors();
        $this->assertSame('2026-11-25', TourDeparture::sole()->visa_deadline->toDateString());
        $rm()->assertSee('25/11/2026')->assertSee('Hạn riêng');

        // Tour trong nước: không hiện ô hạn visa
        $this->tour->update(['type' => 'domestic']);
        Livewire::test(EditTour::class, ['record' => $this->tour->getRouteKey()])->assertFormFieldHidden('visa_deadline_days');
    }

    public function test_don_tour_email_phieu_va_tai_khoan_khach_co_han_visa(): void
    {
        $khach = User::factory()->create();
        $don = Booking::create([
            'tour_id' => $this->tour->id, 'tour_departure_id' => $this->dot('2026-12-15')->id, 'user_id' => $khach->id,
            'customer_name' => 'Nguyễn Văn A', 'customer_phone' => '0909000111', 'customer_email' => 'a@example.com',
            'adults' => 2, 'children' => 0, 'unit_price_adult' => 10_000_000, 'total_price' => 20_000_000,
            'status' => 'confirmed', 'payment_status' => 'unpaid',
        ]);

        // Quan hệ chỉ nạp vài cột (như lúc gửi mail đặt tour) vẫn ra đúng hạn
        $don->load(['tour:id,name,duration_days', 'departure:id,start_date']);
        $this->assertSame('2026-11-15', $don->hanNopVisa()->toDateString());
        $html = (new BookingMail($don, BookingMail::XAC_NHAN))->render();
        $this->assertStringContainsString('Hạn nộp hồ sơ visa', $html);
        $this->assertStringContainsString('15/11/2026', $html);

        // Nhân viên mở đơn trong admin
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $nv = User::factory()->create();
        $nv->givePermissionTo(collect(['ViewAny:Booking', 'View:Booking'])->map(fn ($q) => Permission::findOrCreate($q, 'web')));
        $this->actingAs($nv)->get(BookingResource::getUrl('view', ['record' => $don]))
            ->assertOk()->assertSee('Hạn nộp hồ sơ visa')->assertSee('15/11/2026')->assertSee('Còn 33 ngày');

        Sanctum::actingAs($khach);
        $this->getJson('/api/v1/auth/bookings')->assertOk()->assertJsonPath('data.0.visa_deadline', '15/11/2026');
        $this->postJson('/api/v1/bookings/lookup', ['booking_code' => $don->booking_code, 'phone' => '0909000111'])
            ->assertOk()->assertJsonPath('data.visa_deadline', '15/11/2026');
    }
}
