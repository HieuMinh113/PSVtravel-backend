<?php

namespace Tests\Feature;

use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Resources\Bookings\Pages\ViewBooking;
use App\Filament\Resources\Bookings\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Payment;
use Modules\Tour\Models\Tour;
use Modules\Tour\Models\TourDeparture;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Vai trò theo bộ phận: sale, kế toán, marketing, điều hành, visa. Nhân viên
 * chỉ sửa / xác nhận / thêm khoản thu cho đơn của mình + đơn web chưa ai nhận.
 */
class VaiTroNhanVienTest extends TestCase
{
    use RefreshDatabase;

    private Tour $tour;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->tour = Tour::create(['slug' => 'seoul-'.uniqid(), 'name' => 'Seoul 5N4Đ', 'type' => 'abroad', 'status' => 'published', 'adult_price' => 10_000_000, 'duration_days' => 5]);
    }

    private function nguoi(string $vaiTro): User
    {
        return User::factory()->create()->assignRole($vaiTro);
    }

    private function don(?User $phuTrach, string $trangThai = 'pending'): Booking
    {
        $dot = TourDeparture::create(['tour_id' => $this->tour->id, 'start_date' => now()->addDays(rand(20, 300))->toDateString(), 'seats_total' => 30, 'seats_left' => 30]);
        $b = (new Booking)->forceFill(['source' => $phuTrach ? 'quan_tri' : 'web', 'created_by' => $phuTrach?->id, 'assigned_to' => $phuTrach?->id]);
        $b->fill([
            'tour_id' => $this->tour->id, 'tour_departure_id' => $dot->id,
            'customer_name' => 'Khách '.uniqid(), 'customer_phone' => '0909000111',
            'adults' => 1, 'children' => 0, 'unit_price_adult' => 10_000_000, 'total_price' => 10_000_000,
            'status' => $trangThai, 'payment_status' => 'unpaid',
        ])->save();

        return $b;
    }

    /** @param  array<string, int>  $mong  đường dẫn => mã HTTP mong đợi */
    private function vao(User $u, array $mong): void
    {
        $this->actingAs($u);
        foreach ($mong as $duong => $ma) {
            $this->assertSame($ma, $this->get($duong)->status(), "{$u->getRoleNames()->first()} → {$duong}");
        }
    }

    public function test_migration_tao_5_vai_tro(): void
    {
        foreach (['sale', 'ke_toan', 'marketing', 'dieu_hanh', 'visa'] as $ten) {
            $this->assertTrue(Role::where('name', $ten)->exists(), $ten);
        }
        $this->assertTrue(Role::findByName('ke_toan', 'web')->hasPermissionTo('ViewAll:Attendance'));
        $this->assertTrue(Role::findByName('marketing', 'web')->hasPermissionTo('Create:Tour'));
        $this->assertFalse(Role::findByName('sale', 'web')->hasPermissionTo('Update:Tour'));
    }

    public function test_quyen_vao_trang_cua_tung_vai_tro(): void
    {
        $chung = ['/admin/cham-cong' => 200, '/admin/bang-cham-cong' => 403];

        $this->vao($this->nguoi('sale'), [...$chung,
            '/admin/bookings' => 200, '/admin/bookings/create' => 200, '/admin/thong-ke-doanh-so' => 200,
            '/admin/tours' => 200, '/admin/tours/create' => 403,
            '/admin/visa-checklists' => 200, '/admin/visa-checklists/create' => 403,
            '/admin/visa-cases' => 403, '/admin/tra-cho-lien-minh' => 403, '/admin/khoan-thu' => 403,
        ]);
        $this->vao($this->nguoi('ke_toan'), [
            '/admin/cham-cong' => 200, '/admin/bang-cham-cong' => 200, '/admin/bang-cong-thang' => 200,
            '/admin/khuon-mat-nhan-vien' => 200, '/admin/cau-hinh-cham-cong' => 200,
            '/admin/khoan-thu' => 200, '/admin/thong-ke-doanh-so' => 200, '/admin/bookings' => 200,
            '/admin/bookings/create' => 403, '/admin/tours' => 403,
        ]);
        $this->vao($this->nguoi('marketing'), [...$chung,
            '/admin/tours' => 200, '/admin/tours/create' => 200, '/admin/bookings/create' => 200,
            '/admin/visa-checklists' => 403, '/admin/tra-cho-lien-minh' => 403,
        ]);
        $this->vao($this->nguoi('dieu_hanh'), [...$chung,
            '/admin/tra-cho-lien-minh' => 200, '/admin/alliance-sources' => 200, '/admin/alliance-sources/create' => 200,
            '/admin/bookings/create' => 200, '/admin/visa-cases' => 403,
        ]);
        $this->vao($this->nguoi('visa'), [...$chung,
            '/admin/visa-cases' => 200, '/admin/visa-cases/create' => 200, '/admin/visa-checklists/create' => 200,
            '/admin/visa-countries/create' => 200, '/admin/visa-providers' => 200,
            '/admin/tours' => 200, '/admin/tours/create' => 403, '/admin/bookings/create' => 200,
        ]);
    }

    public function test_chi_sua_xac_nhan_them_khoan_thu_don_cua_minh(): void
    {
        $lan = $this->nguoi('sale');
        $hoa = $this->nguoi('marketing');
        $cuaLan = $this->don($lan);
        $cuaHoa = $this->don($hoa);
        $web = $this->don(null);
        $daChotCuaHoa = $this->don($hoa, 'confirmed');

        $this->actingAs($lan);
        $this->assertTrue($lan->can('update', $cuaLan));
        $this->assertTrue($lan->can('update', $web));     // đơn web chưa ai nhận
        $this->assertFalse($lan->can('update', $cuaHoa));
        $this->get('/admin/bookings/'.$cuaHoa->id)->assertOk();          // xem được
        $this->get('/admin/bookings/'.$cuaHoa->id.'/edit')->assertForbidden();
        $this->get('/admin/bookings/'.$cuaLan->id.'/edit')->assertOk();

        Livewire::test(ListBookings::class)
            ->assertTableActionVisible('confirm', $cuaLan)
            ->assertTableActionVisible('confirm', $web)
            ->assertTableActionHidden('confirm', $cuaHoa)
            ->assertTableActionHidden('cancel', $cuaHoa)
            ->assertTableActionHidden('complete', $daChotCuaHoa);

        // Xác nhận đơn web → thành của Lan
        Livewire::test(ListBookings::class)->callTableAction('confirm', $web);
        $this->assertSame($lan->id, $web->fresh()->assigned_to);
        $this->assertFalse($hoa->can('update', $web->fresh()));

        // Khoản thu: thêm được ở đơn của mình, không ở đơn người khác
        Livewire::test(PaymentsRelationManager::class, ['ownerRecord' => $cuaLan, 'pageClass' => ViewBooking::class])
            ->assertActionVisible(TestAction::make('create')->table());
        Livewire::test(PaymentsRelationManager::class, ['ownerRecord' => $cuaHoa, 'pageClass' => ViewBooking::class])
            ->assertActionHidden(TestAction::make('create')->table());
        $p = Payment::create(['booking_id' => $cuaHoa->id, 'amount' => 1_000_000, 'method' => 'cash', 'status' => 'pending', 'paid_at' => now()]);
        $this->assertFalse($lan->can('update', $p));
        $this->assertTrue($hoa->can('update', $p));

        // Kế toán duyệt khoản thu đơn ai cũng được, nhưng không sửa đơn
        $kt = $this->nguoi('ke_toan');
        $this->assertTrue($kt->can('duyet', $p));
        $this->assertFalse($kt->can('update', $cuaHoa));
    }

    /** Người quản lý vai trò (không dùng super_admin vì bị bắt bật 2FA). */
    private function nguoiSuaVaiTro(): User
    {
        $u = User::factory()->create();
        foreach (['ViewAny', 'View', 'Create', 'Update'] as $q) {
            $u->givePermissionTo(Permission::findOrCreate("{$q}:Role", 'web'));
        }

        return $u;
    }

    public function test_luu_vai_tro_khong_lam_mat_quyen_dac_biet(): void
    {
        $this->actingAs($this->nguoiSuaVaiTro());
        $kt = Role::findByName('ke_toan', 'web');
        // Quyền không có ô trên màn hình (điểm bản đồ) cũng phải giữ nguyên
        $kt->givePermissionTo(Permission::findOrCreate('Create:GuideSpot', 'web'));
        $truoc = $kt->fresh()->permissions->pluck('name')->sort()->values()->all();
        $this->assertContains('Approve:Payment', $truoc);

        // Mở ra, không đổi gì, bấm Lưu
        Livewire::test(EditRole::class, ['record' => $kt->getRouteKey()])->call('save')->assertHasNoFormErrors();
        $this->assertSame($truoc, $kt->fresh()->permissions->pluck('name')->sort()->values()->all());

        // Quyền đặc biệt có ô ở thẻ riêng → bỏ tích được
        Livewire::test(EditRole::class, ['record' => $kt->getRouteKey()])
            ->assertSee('Duyệt Khoản Thu')
            ->fillForm(['custom_permissions_tab' => ['ViewAll:Booking', 'ViewAll:Attendance']])
            ->call('save')
            ->assertHasNoFormErrors();
        $sau = $kt->fresh()->permissions->pluck('name');
        $this->assertNotContains('Approve:Payment', $sau);
        $this->assertContains('ViewAll:Booking', $sau);
        $this->assertContains('Create:GuideSpot', $sau);
        $this->assertContains('ViewAny:Booking', $sau);
    }

    public function test_migration_tra_lai_quyen_dac_biet_da_bi_xoa(): void
    {
        $kt = Role::findByName('ke_toan', 'web');
        $admin = Role::findOrCreate('admin', 'web');
        $kt->revokePermissionTo(['Approve:Payment', 'ViewAll:Booking', 'ViewAll:Attendance']);
        $sale = Role::findByName('sale', 'web');

        (require database_path('migrations/2026_10_29_090000_tra_lai_quyen_dac_biet.php'))->up();

        foreach (['Approve:Payment', 'ViewAll:Booking', 'ViewAll:Attendance'] as $q) {
            $this->assertTrue($kt->fresh()->hasPermissionTo($q), $q);
            $this->assertTrue($admin->fresh()->hasPermissionTo($q), $q);
        }
        $this->assertTrue($admin->fresh()->hasPermissionTo('ViewAll:VisaCase'));
        $this->assertFalse($kt->fresh()->hasPermissionTo('ViewAll:VisaCase'));
        $this->assertFalse($sale->fresh()->hasPermissionTo('Approve:Payment'));
    }
}
