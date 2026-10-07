<?php

namespace Tests\Feature;

use App\Filament\Pages\ThongKeDoanhSo;
use App\Filament\Resources\Bookings\Pages\CreateBooking;
use App\Filament\Resources\Bookings\Pages\EditBooking;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Resources\Bookings\Pages\ViewBooking;
use App\Filament\Resources\Bookings\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Booking\Database\Seeders\QuyenDonTourSeeder;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Payment;
use Modules\Tour\Models\Tour;
use Modules\Tour\Models\TourDeparture;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Người tạo / người xác nhận / người phụ trách (hưởng hoa hồng) của đơn,
 * ảnh chuyển khoản + kế toán duyệt khoản thu, thống kê doanh số theo tháng.
 */
class HoaHongVaKhoanThuTest extends TestCase
{
    use RefreshDatabase;

    private Tour $tour;

    private TourDeparture $dot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-07 09:00'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Storage::fake('rieng');
        (new QuyenDonTourSeeder)->run();

        $this->tour = Tour::create(['slug' => 'seoul-'.uniqid(), 'name' => 'Seoul 5N4Đ', 'type' => 'abroad', 'status' => 'published', 'adult_price' => 10_000_000, 'duration_days' => 5]);
        $this->dot = TourDeparture::create(['tour_id' => $this->tour->id, 'start_date' => '2026-11-20', 'seats_total' => 30, 'seats_left' => 30]);
    }

    private function nhanVien(): User
    {
        $vaiTro = Role::findOrCreate('sale', 'web');
        $vaiTro->givePermissionTo(collect(['ViewAny:Booking', 'View:Booking', 'Create:Booking', 'Update:Booking'])
            ->map(fn ($q) => Permission::findOrCreate($q, 'web')));

        return User::factory()->create()->assignRole($vaiTro);
    }

    private function quanLy(): User
    {
        return $this->nhanVien()->givePermissionTo('ViewAll:Booking', 'Approve:Payment');
    }

    private function keToan(): User
    {
        return User::factory()->create()->assignRole('ke_toan');
    }

    private function don(User $phuTrach, array $them = []): Booking
    {
        $this->actingAs($phuTrach);
        $b = Booking::create([
            'tour_id' => $this->tour->id, 'tour_departure_id' => $this->dot->id,
            'customer_name' => 'Khách '.uniqid(), 'customer_phone' => '0909000111',
            'adults' => 2, 'children' => 0, 'unit_price_adult' => 10_000_000, 'total_price' => 20_000_000,
            'status' => 'confirmed', 'payment_status' => 'unpaid', 'deposit_percent' => 30,
            ...$them,
        ]);
        auth()->logout();

        return $b;
    }

    private function thu(Booking $b, int $soTien, string $trangThai = 'success'): Payment
    {
        return Payment::create(['booking_id' => $b->id, 'amount' => $soTien, 'method' => 'cash', 'status' => $trangThai, 'paid_at' => now()]);
    }

    public function test_don_ghi_nguoi_tao_nguoi_xac_nhan_va_nguoi_phu_trach(): void
    {
        $a = $this->nhanVien();
        $b = $this->nhanVien();

        $this->actingAs($a);
        Livewire::test(CreateBooking::class)
            ->fillForm([
                'tour_id' => $this->tour->id, 'tour_departure_id' => $this->dot->id,
                'customer_name' => 'Trần Thị B', 'customer_phone' => '0912000111',
                'adults' => 1, 'children' => 0, 'unit_price_adult' => 10_000_000,
                'assigned_to' => $b->id, // nhân viên thường không đổi được → bỏ qua
            ])
            ->call('create')
            ->assertHasNoFormErrors();
        $don = Booking::where('customer_name', 'Trần Thị B')->sole();
        $this->assertSame([$a->id, $a->id, null], [$don->created_by, $don->assigned_to, $don->confirmed_by]);

        // B bấm xác nhận: B là người xác nhận, hoa hồng vẫn của A (người tạo)
        $this->actingAs($b);
        Livewire::test(ListBookings::class)->callTableAction('confirm', $don);
        $don->refresh();
        $this->assertSame([$a->id, $a->id, $b->id], [$don->created_by, $don->assigned_to, $don->confirmed_by]);
        $this->assertSame('2026-10-07', $don->confirmed_at->toDateString());

        // Nhân viên không đổi được người phụ trách; quản lý thì được
        Livewire::test(EditBooking::class, ['record' => $don->getRouteKey()])
            ->assertFormFieldIsDisabled('assigned_to')
            ->fillForm(['assigned_to' => $b->id])->call('save');
        $this->assertSame($a->id, $don->fresh()->assigned_to);

        $ql = $this->quanLy();
        $this->actingAs($ql);
        Livewire::test(EditBooking::class, ['record' => $don->getRouteKey()])
            ->assertFormFieldIsEnabled('assigned_to')
            ->fillForm(['assigned_to' => $b->id])->call('save')->assertHasNoFormErrors();
        $this->assertSame($b->id, $don->fresh()->assigned_to);
        $this->assertSame($a->id, $don->fresh()->created_by);
    }

    public function test_don_khach_dat_web_khong_co_nguoi_tao(): void
    {
        $this->postJson('/api/v1/bookings', [
            'tour_id' => $this->tour->id, 'tour_departure_id' => $this->dot->id,
            'customer_name' => 'Khách Web', 'customer_phone' => '0909123456', 'adults' => 1,
        ])->assertSuccessful();
        $don = Booking::where('customer_name', 'Khách Web')->sole();
        $this->assertSame('web', $don->source);
        $this->assertNull($don->created_by);
        $this->assertNull($don->assigned_to);
    }

    public function test_khoan_thu_can_anh_chuyen_khoan_va_cho_ke_toan_duyet(): void
    {
        $nv = $this->nhanVien();
        $kt = $this->keToan();
        $don = $this->don($nv);
        $this->actingAs($nv);

        $rm = fn () => Livewire::test(PaymentsRelationManager::class, ['ownerRecord' => $don, 'pageClass' => ViewBooking::class]);

        // Chuyển khoản mà không có ảnh → lỗi
        $rm()->callTableAction('create', data: ['amount' => 6_000_000, 'method' => 'bank_transfer', 'paid_at' => now()])
            ->assertHasTableActionErrors(['proof_images']);
        $this->assertSame(0, Payment::count());

        // Có ảnh → lưu ở trạng thái chờ duyệt, chưa tính vào đã thu
        $rm()->callTableAction('create', data: [
            'amount' => 6_000_000, 'method' => 'bank_transfer', 'paid_at' => now(),
            'proof_images' => [UploadedFile::fake()->image('bien-lai.jpg')],
        ])->assertHasNoTableActionErrors();
        $p = Payment::sole();
        $this->assertSame('pending', $p->status);
        $this->assertSame($nv->id, $p->received_by);
        $this->assertCount(1, $p->proof_images);
        Storage::disk('rieng')->assertExists($p->proof_images[0]);
        $this->assertSame(0, $don->fresh()->paid_total);
        $this->assertSame('unpaid', $don->fresh()->payment_status);
        $this->assertSame(1, $kt->notifications()->count());

        // Nhân viên không tự duyệt được, không vào trang Duyệt khoản thu
        $this->assertFalse($nv->can('duyet', $p));
        $this->get('/admin/khoan-thu')->assertForbidden();

        // Kế toán duyệt
        $this->actingAs($kt);
        $this->get('/admin/khoan-thu')->assertOk();
        $this->get('/admin/bookings/'.$don->id)->assertOk();
        $this->get('/admin/bookings/'.$don->id.'/edit')->assertForbidden();
        Livewire::test(ListPayments::class)
            ->assertCanSeeTableRecords([$p])
            ->callTableAction('duyetKhoanThu', $p);
        $p->refresh();
        $this->assertSame('success', $p->status);
        $this->assertSame($kt->id, $p->approved_by);
        $don->refresh();
        $this->assertSame(6_000_000, $don->paid_total);
        $this->assertSame('du_coc', $don->tinhTrangTien());
        $this->assertSame(1, $nv->notifications()->count());

        // Đã duyệt thì nhân viên không sửa được nữa
        $this->assertFalse($nv->can('update', $p));

        // Từ chối: ghi lý do, không tính tiền
        $p2 = $this->thu($don, 1_000_000, 'pending');
        $p2->forceFill(['received_by' => $nv->id])->save();
        Livewire::test(ListPayments::class)
            ->callTableAction('tuChoiKhoanThu', $p2, data: ['ly_do' => 'Không thấy tiền về']);
        $this->assertSame('failed', $p2->fresh()->status);
        $this->assertStringContainsString('Không thấy tiền về', $p2->fresh()->note);
        $this->assertSame(6_000_000, $don->fresh()->paid_total);

        // Xoá khoản thu → xoá luôn ảnh
        $duong = $p->proof_images[0];
        $p->delete();
        Storage::disk('rieng')->assertMissing($duong);
    }

    public function test_thong_ke_theo_thang_va_nguoi_phu_trach(): void
    {
        $a = $this->nhanVien();
        $b = $this->nhanVien();

        $a1 = $this->don($a);                                   // chưa cọc
        $this->thu($a2 = $this->don($a), 3_000_000);            // đang cọc (chưa đủ 30%)
        $this->thu($a3 = $this->don($a, ['status' => 'completed']), 20_000_000); // trả đủ + hoàn thành
        $this->don($a)->update(['status' => 'cancelled']);      // chốt rồi huỷ: không tính doanh số, đếm riêng
        $this->thu($b1 = $this->don($b), 6_000_000);            // đủ cọc
        $this->thu($this->don($b), 6_000_000, 'pending');       // chờ duyệt: không tính

        // Đơn chốt tháng trước — không vào tháng này
        $this->travelTo(Carbon::parse('2026-09-15'));
        $cu = $this->don($a);
        $this->travelBack();
        $this->travelTo(Carbon::parse('2026-10-07 09:00'));

        $this->assertSame(['chua_coc', 'dang_coc', 'da_thu_du', 'du_coc'], [$a1->tinhTrangTien(), $a2->fresh()->tinhTrangTien(), $a3->fresh()->tinhTrangTien(), $b1->fresh()->tinhTrangTien()]);

        // Quản lý: thấy mọi người
        $this->actingAs($this->quanLy());
        $trang = Livewire::test(ThongKeDoanhSo::class)->assertOk()
            ->assertCanNotSeeTableRecords([$cu])
            ->assertSee('Tổng hợp tháng 10/2026');
        $dong = $trang->instance()->tongHop()->keyBy('ten');
        $this->assertSame(3, $dong[$a->name]['so_don']);
        $this->assertSame(60_000_000, $dong[$a->name]['doanh_so']);
        $this->assertSame(23_000_000, $dong[$a->name]['da_thu']);
        $this->assertSame([1, 1, 1, 1, 1], [$dong[$a->name]['chua_coc'], $dong[$a->name]['dang_coc'], $dong[$a->name]['da_thu_du'], $dong[$a->name]['hoan_thanh'], $dong[$a->name]['huy']]);
        $this->assertSame(2, $dong[$b->name]['so_don']);
        $this->assertSame(6_000_000, $dong[$b->name]['da_thu']);
        $this->assertSame(5, $dong['TỔNG CỘNG']['so_don']);

        $trang->filterTable('tien', 'da_thu_du')->assertCanSeeTableRecords([$a3])->assertCanNotSeeTableRecords([$a1, $b1]);
        $trang->filterTable('tien', null)->filterTable('thang', '2026-09')->assertCanSeeTableRecords([$cu])->assertCanNotSeeTableRecords([$a1]);

        Livewire::test(ThongKeDoanhSo::class)->callAction('xuatExcel')->assertFileDownloaded('Thong-ke-doanh-so-10-2026.xlsx');

        // Nhân viên: chỉ thấy đơn của mình
        $this->actingAs($b);
        $trang = Livewire::test(ThongKeDoanhSo::class)->assertCanSeeTableRecords([$b1])->assertCanNotSeeTableRecords([$a1, $a3]);
        $this->assertSame([$b->name], $trang->instance()->tongHop()->pluck('ten')->all());

        // Khách hàng / người không có quyền đơn: không vào được
        $this->actingAs(User::factory()->create());
        $this->get('/admin/thong-ke-doanh-so')->assertForbidden();
    }
}
