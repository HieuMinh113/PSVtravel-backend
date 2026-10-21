<?php

namespace Tests\Feature;

use App\Filament\Resources\Bookings\Pages\CreateBooking;
use App\Filament\Resources\Bookings\Pages\EditBooking;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Resources\Tours\Pages\EditTour;
use App\Filament\Resources\Tours\RelationManagers\DeparturesRelationManager;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Payment;
use Modules\Guide\Models\Guide;
use Modules\Tour\Models\Tour;
use Modules\Tour\Models\TourDeparture;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Số chỗ và tình trạng thanh toán luôn khớp khi nhân viên sửa đơn: đơn đã xác
 * nhận đổi số khách / đổi đợt, tổng tiền đổi sau khi đã thu, xoá đợt còn đơn.
 */
class SoChoVaThanhToanTest extends TestCase
{
    use RefreshDatabase;

    private Tour $tour;

    private TourDeparture $a;

    private TourDeparture $b;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $nv = User::factory()->create();
        foreach (['ViewAny', 'View', 'Create', 'Update', 'ViewAll'] as $q) {
            $nv->givePermissionTo(Permission::findOrCreate("{$q}:Booking", 'web'));
        }
        foreach (['ViewAny', 'View', 'Update'] as $q) {
            $nv->givePermissionTo(Permission::findOrCreate("{$q}:Tour", 'web'));
        }
        $this->actingAs($nv);
        $this->tour = Tour::create(['slug' => 'da-lat', 'name' => 'Đà Lạt 3N2Đ', 'type' => 'domestic', 'status' => 'published', 'adult_price' => 1_000_000, 'duration_days' => 3]);
        $this->a = TourDeparture::create(['tour_id' => $this->tour->id, 'start_date' => now()->addDays(20)->toDateString(), 'seats_total' => 10, 'seats_left' => 10]);
        $this->b = TourDeparture::create(['tour_id' => $this->tour->id, 'start_date' => now()->addDays(30)->toDateString(), 'seats_total' => 10, 'seats_left' => 10]);
    }

    private function don(int $nguoiLon = 2, string $trangThai = 'pending'): Booking
    {
        return Booking::create(['tour_id' => $this->tour->id, 'tour_departure_id' => $this->a->id, 'customer_name' => 'Nguyễn Văn A', 'customer_phone' => '0909000111',
            'adults' => $nguoiLon, 'children' => 0, 'unit_price_adult' => 1_000_000, 'total_price' => $nguoiLon * 1_000_000, 'status' => $trangThai, 'payment_status' => 'unpaid']);
    }

    private function cho(): array
    {
        return [$this->a->fresh()->seats_left, $this->b->fresh()->seats_left];
    }

    public function test_don_da_xac_nhan_doi_so_khach_va_doi_dot_thi_so_cho_di_theo(): void
    {
        $don = $this->don();
        Livewire::test(ListBookings::class)->callTableAction('confirm', $don);
        $this->assertSame([8, 10], $this->cho());

        // Thêm khách cùng đợt: trừ thêm phần chênh
        Livewire::test(EditBooking::class, ['record' => $don->getRouteKey()])->fillForm(['adults' => 4])->call('save')->assertHasNoFormErrors();
        $this->assertSame([6, 10], $this->cho());

        // Đổi sang đợt B, 5 khách: trả 4 chỗ cho A, trừ 5 chỗ ở B
        Livewire::test(EditBooking::class, ['record' => $don->getRouteKey()])
            ->assertSee('đổi đợt hoặc số khách thì hệ thống tự trả chỗ')
            ->fillForm(['adults' => 5, 'tour_departure_id' => $this->b->id])->call('save')->assertHasNoFormErrors();
        $this->assertSame([10, 5], $this->cho());

        // Huỷ: hoàn đúng 5 chỗ vào B — đợt 10 chỗ không thành 15 nữa
        Livewire::test(ListBookings::class)->callTableAction('cancel', $don->fresh(), data: ['cancel_reason' => 'Khách đổi kế hoạch']);
        $this->assertSame([10, 10], $this->cho());
    }

    public function test_doi_sang_dot_khong_du_cho_thi_bao_loi_va_khong_luu(): void
    {
        $khac = $this->don(9);
        $khac->update(['tour_departure_id' => $this->b->id]);
        Livewire::test(ListBookings::class)->callTableAction('confirm', $khac);
        $don = $this->don();
        Livewire::test(ListBookings::class)->callTableAction('confirm', $don);
        $this->assertSame([8, 1], $this->cho());

        Livewire::test(EditBooking::class, ['record' => $don->getRouteKey()])
            ->fillForm(['tour_departure_id' => $this->b->id])
            ->call('save')
            ->assertNotified('Không lưu được');
        $this->assertSame($this->a->id, $don->fresh()->tour_departure_id);
        $this->assertSame([8, 1], $this->cho());

        // Cùng đợt mà thêm quá số chỗ còn
        Livewire::test(EditBooking::class, ['record' => $don->getRouteKey()])->fillForm(['adults' => 11])->call('save')->assertNotified('Không lưu được');
        $this->assertSame(2, $don->fresh()->adults);
        $this->assertSame([8, 1], $this->cho());

        // Vừa đủ thì được, đợt chuyển "Hết chỗ"
        Livewire::test(EditBooking::class, ['record' => $don->getRouteKey()])->fillForm(['adults' => 10])->call('save')->assertHasNoFormErrors();
        $this->assertSame([0, 1], $this->cho());
        $this->assertSame('full', $this->a->fresh()->status);
    }

    public function test_don_chua_xac_nhan_sua_so_khach_khong_dong_toi_so_cho(): void
    {
        $don = $this->don();
        Livewire::test(EditBooking::class, ['record' => $don->getRouteKey()])->fillForm(['adults' => 6, 'tour_departure_id' => $this->b->id])->call('save')->assertHasNoFormErrors();
        $this->assertSame([10, 10], $this->cho());
    }

    public function test_doi_tong_tien_sau_khi_da_thu_thi_tinh_lai_tinh_trang_thanh_toan(): void
    {
        $don = $this->don(2, 'confirmed');
        Payment::create(['booking_id' => $don->id, 'amount' => 2_000_000, 'method' => 'bank_transfer', 'status' => 'success', 'paid_at' => now()]);
        $this->assertSame('paid', $don->fresh()->payment_status);

        // Thêm 1 khách → tổng 3 triệu, đã thu 2 triệu
        Livewire::test(EditBooking::class, ['record' => $don->getRouteKey()])->fillForm(['adults' => 3])->call('save')->assertHasNoFormErrors();
        $don = $don->fresh();
        $this->assertSame(3_000_000, $don->total_price);
        $this->assertSame('partial', $don->payment_status);
        $this->assertSame(2_000_000, $don->paid_total);

        // Bớt lại 2 khách → đủ tiền
        Livewire::test(EditBooking::class, ['record' => $don->getRouteKey()])->fillForm(['adults' => 2])->call('save');
        $this->assertSame('paid', $don->fresh()->payment_status);
    }

    public function test_tao_don_luon_chua_tra_cho_toi_khi_co_khoan_thu(): void
    {
        Livewire::test(CreateBooking::class)
            ->fillForm([
                'tour_id' => $this->tour->id, 'customer_name' => 'Trần B', 'customer_phone' => '0909000222',
                'adults' => 1, 'children' => 0, 'unit_price_adult' => 1_000_000, 'total_price' => 1_000_000, 'payment_status' => 'paid',
            ])
            ->assertFormFieldDisabled('payment_status')
            ->call('create')
            ->assertHasNoFormErrors();
        $this->assertSame('unpaid', Booking::where('customer_name', 'Trần B')->sole()->payment_status);
    }

    public function test_khong_xoa_duoc_ngay_khoi_hanh_con_don(): void
    {
        $don = $this->don();
        $rm = fn () => Livewire::test(DeparturesRelationManager::class, ['ownerRecord' => $this->tour, 'pageClass' => EditTour::class]);

        $rm()->callAction(TestAction::make('delete')->table($this->a))->assertNotified('Không xoá được ngày khởi hành này');
        $this->assertModelExists($this->a);
        $this->assertSame($this->a->id, $don->fresh()->tour_departure_id);
        // Ở tầng dữ liệu cũng chặn
        $this->assertFalse($this->a->fresh()->delete());

        // Đơn đã huỷ thì xoá được; ngày không có đơn cũng xoá được
        $don->update(['status' => 'cancelled']);
        $rm()->callAction(TestAction::make('delete')->table($this->a));
        $this->assertModelMissing($this->a);
        $rm()->callAction(TestAction::make('delete')->table($this->b));
        $this->assertModelMissing($this->b);
    }

    public function test_luot_xem_cam_nang_doc_tu_cau_hinh(): void
    {
        $bai = Guide::create(['title' => 'Bài A', 'slug' => 'bai-a', 'status' => 'published', 'published_at' => now()->subDay()]);

        config(['guide.luot_xem_cach_nhau_phut' => 60]);
        $this->postJson('/api/v1/guides/bai-a/view');
        $this->postJson('/api/v1/guides/bai-a/view');
        $this->assertSame(1, $bai->fresh()->view_count);

        config(['guide.luot_xem_cach_nhau_phut' => 0]);
        $this->postJson('/api/v1/guides/bai-a/view');
        $this->assertSame(2, $bai->fresh()->view_count);
    }
}
