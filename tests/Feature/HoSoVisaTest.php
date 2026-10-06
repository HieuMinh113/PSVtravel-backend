<?php

namespace Tests\Feature;

use App\Filament\Resources\VisaCases\Pages\CreateVisaCase;
use App\Filament\Resources\VisaCases\Pages\EditVisaCase;
use App\Filament\Resources\VisaCases\Pages\ListVisaCases;
use App\Filament\Resources\VisaCases\VisaCaseResource;
use App\Filament\Resources\VisaChecklists\Pages\ListVisaChecklists;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Visa\Models\VisaCase;
use Modules\Visa\Models\VisaChecklist;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Hồ sơ visa theo từng case: mẫu checklist, tự chọn mẫu, đánh dấu giấy tờ,
 * tin nhắn giấy thiếu, file riêng tư, phân quyền vai trò "visa".
 */
class HoSoVisaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-06 09:00'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function nhanVienVisa(): User
    {
        $u = User::factory()->create(['name' => 'Chị Visa']);
        $u->assignRole(Role::findByName('visa', 'web'));

        return $u;
    }

    private function mau(string $ten): VisaChecklist
    {
        return VisaChecklist::where('name', $ten)->firstOrFail();
    }

    public function test_migration_tao_vai_tro_visa_va_mau_khoi_dau(): void
    {
        $vaiTro = Role::findByName('visa', 'web');
        $this->assertTrue($vaiTro->hasPermissionTo('Update:VisaCase'));
        $this->assertTrue($vaiTro->hasPermissionTo('Create:VisaChecklist'));
        $this->assertFalse($vaiTro->hasPermissionTo(Permission::findOrCreate('ViewAny:Tour', 'web')));

        $this->assertSame(8, VisaChecklist::count());
        $tq = $this->mau('Trung Quốc — Du lịch — Nhân viên');
        $this->assertSame('Hộ chiếu bản gốc', $tq->items[0]['ten']);
        $this->assertSame('Xác nhận việc làm', collect($tq->items)->last()['ten']);
    }

    public function test_tu_chon_mau_khop_nhat(): void
    {
        $this->assertSame('Trung Quốc — Du lịch — Nhân viên', VisaChecklist::timMau('trung quốc ', 'du_lich', 'nhan_vien')?->name);
        // Mẫu công tác ghi "chung" đối tượng → dùng cho mọi đối tượng
        $this->assertSame('Trung Quốc — Công tác', VisaChecklist::timMau('Trung Quốc', 'cong_tac', 'nhan_vien')?->name);
        // Mẫu Hàn ghi rõ học sinh → không đưa cho người đi làm
        $this->assertNull(VisaChecklist::timMau('Hàn Quốc', 'du_lich', 'nhan_vien'));
        $this->assertSame('Ai Cập — Du lịch', VisaChecklist::timMau('Ai Cập', 'du_lich', 'huu_tri')?->name);
        $this->assertNull(VisaChecklist::timMau(null, 'du_lich', null));

        // Mẫu tắt thì không chọn
        $this->mau('Ai Cập — Du lịch')->update(['is_active' => false]);
        $this->assertNull(VisaChecklist::timMau('Ai Cập', 'du_lich', null));
    }

    public function test_ma_ho_so_tien_do_giay_to_va_tin_nhan(): void
    {
        $hs = VisaCase::create([
            'full_name' => 'NGUYEN VAN A', 'country' => 'Trung Quốc',
            'travel_date' => '2026-11-01', 'passport_expiry' => '2027-03-01',
            'fee' => 1_500_000, 'paid' => 500_000,
            'checklist' => [
                ['ten' => 'Hộ chiếu bản gốc', 'trang_thai' => 'da_nhan'],
                ['ten' => 'CCCD photo', 'trang_thai' => 'thieu'],
                ['ten' => 'Hộ khẩu', 'ghi_chu' => 'sao y A4', 'trang_thai' => 'thieu'],
                ['ten' => 'Thư mời', 'trang_thai' => 'khong_can'],
            ],
        ]);

        // Postgres không quay lại bộ đếm id giữa các bài kiểm thử → so theo id
        $this->assertSame('HSV-'.str_pad((string) $hs->id, 5, '0', STR_PAD_LEFT), $hs->fresh()->code);
        $this->assertSame([1, 3], $hs->tienDoGiayTo());
        $this->assertSame(['CCCD photo', 'Hộ khẩu (sao y A4)'], $hs->giayConThieu());
        $this->assertStringContainsString("1. CCCD photo\n2. Hộ khẩu (sao y A4)", $hs->tinNhanGiayThieu());
        $this->assertSame(1_000_000, $hs->conPhaiThu());
        // Hết hạn 01/03/2027 < ngày đi + 6 tháng (01/05/2027)
        $this->assertTrue($hs->hoChieuSapHetHan());
        $this->assertFalse($hs->fill(['passport_expiry' => '2028-01-01'])->hoChieuSapHetHan());
    }

    public function test_tao_ho_so_tu_chep_mau_theo_nuoc_muc_dich_doi_tuong(): void
    {
        $nv = $this->nhanVienVisa();
        $this->actingAs($nv);
        $mau = $this->mau('Trung Quốc — Du lịch — Hưu trí');

        Livewire::test(CreateVisaCase::class)
            ->fillForm([
                'full_name' => 'TRAN THI B',
                'phone' => '0909000111',
                'country' => 'Trung Quốc',
                'purpose' => 'du_lich',
            ])
            ->fillForm(['profile' => 'huu_tri'])
            ->assertFormSet(['visa_checklist_id' => $mau->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $hs = VisaCase::firstOrFail();
        $this->assertSame($mau->id, $hs->visa_checklist_id);
        $this->assertCount(count($mau->items), $hs->checklist);
        $this->assertSame('thieu', $hs->checklist[0]['trang_thai']);
        $this->assertSame('Giấy tờ cá nhân', $hs->checklist[0]['nhom']);
        $this->assertSame($nv->id, $hs->created_by);
        $this->assertSame($nv->id, $hs->assigned_to);
        $this->assertSame('moi', $hs->status);
    }

    public function test_doi_mau_khi_da_nhan_giay_chi_them_giay_moi_khong_xoa(): void
    {
        $this->actingAs($this->nhanVienVisa());
        $hs = VisaCase::create([
            'full_name' => 'LE VAN C', 'country' => 'Trung Quốc', 'purpose' => 'du_lich', 'profile' => 'nhan_vien',
            'checklist' => [
                ['ten' => 'Hộ chiếu bản gốc', 'trang_thai' => 'da_nhan'],
                ['ten' => 'Sổ đỏ', 'trang_thai' => 'thieu'],
            ],
        ]);

        Livewire::test(EditVisaCase::class, ['record' => $hs->getRouteKey()])
            ->assertOk()
            ->assertSee('Đã nhận 1/2 giấy tờ')
            ->fillForm(['visa_checklist_id' => $this->mau('Trung Quốc — Công tác')->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $ds = collect($hs->fresh()->checklist);
        $this->assertSame('da_nhan', $ds->firstWhere('ten', 'Hộ chiếu bản gốc')['trang_thai']);
        $this->assertNotNull($ds->firstWhere('ten', 'Sổ đỏ'));
        $this->assertNotNull($ds->firstWhere('ten', 'Quyết định cử đi công tác'));
        $this->assertSame(1, $ds->where('ten', 'Hộ chiếu bản gốc')->count());
    }

    public function test_danh_sach_tab_loc_va_tin_nhan_giay_thieu(): void
    {
        $nv = $this->nhanVienVisa();
        $this->actingAs($nv);
        $dangGom = VisaCase::create(['full_name' => 'KHACH GOM', 'country' => 'Hàn Quốc', 'status' => 'dang_gom',
            'appointment_at' => '2026-10-07 09:00', 'checklist' => [['ten' => 'Form khai', 'trang_thai' => 'thieu']]]);
        $daNop = VisaCase::create(['full_name' => 'KHACH NOP', 'country' => 'Ai Cập', 'status' => 'da_nop', 'assigned_to' => null]);
        $dau = VisaCase::create(['full_name' => 'KHACH DAU', 'country' => 'Ai Cập', 'status' => 'dau']);

        Livewire::test(ListVisaCases::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$dangGom])
            ->assertCanNotSeeTableRecords([$daNop, $dau])
            ->assertSee('0/1')
            ->set('activeTab', 'tat_ca')
            ->assertCanSeeTableRecords([$dangGom, $daNop, $dau])
            ->filterTable('country', 'Ai Cập')
            ->assertCanNotSeeTableRecords([$dangGom])
            ->resetTableFilters()
            ->filterTable('sap_hen', true)
            ->assertCanSeeTableRecords([$dangGom])
            ->assertCanNotSeeTableRecords([$dau])
            ->resetTableFilters()
            ->searchTable($daNop->fresh()->code)
            ->assertCanSeeTableRecords([$daNop])
            ->assertCanNotSeeTableRecords([$dangGom]);

        Livewire::test(ListVisaCases::class)
            ->mountTableAction('tinNhanGiayThieu', $dangGom)
            ->assertHasNoErrors()
            ->assertSet('mountedActions.0.data.tin', $dangGom->tinNhanGiayThieu());

        // Lịch hẹn ngày mai → huy hiệu menu
        $this->assertSame('1', VisaCaseResource::getNavigationBadge());

        Livewire::test(ListVisaChecklists::class)->assertOk()->assertSee('Ai Cập — Du lịch');
    }

    public function test_file_ho_so_chi_mo_bang_link_co_chu_ky(): void
    {
        Storage::fake('rieng');
        $this->actingAs($this->nhanVienVisa());
        $hs = VisaCase::create(['full_name' => 'KHACH FILE', 'country' => 'Ai Cập']);

        Livewire::test(EditVisaCase::class, ['record' => $hs->getRouteKey()])
            ->fillForm(['files' => [UploadedFile::fake()->create('ho-chieu.pdf', 100, 'application/pdf')]])
            ->call('save')
            ->assertHasNoFormErrors();

        $hs->refresh();
        $this->assertCount(1, $hs->files);
        $this->assertStringStartsWith('ho-so-visa/', $hs->files[0]);
        $this->assertSame('ho-chieu.pdf', array_values($hs->file_names)[0]);
        Storage::disk('rieng')->assertExists($hs->files[0]);

        // Xoá hẳn hồ sơ → xoá luôn file của khách
        $hs->forceDelete();
        Storage::disk('rieng')->assertMissing($hs->files[0]);
    }

    public function test_link_tep_rieng_khong_co_chu_ky_bi_chan(): void
    {
        $this->get('/tep-rieng/ho-so-visa/bat-ky.pdf')->assertForbidden();
        $this->assertStringContainsString('/tep-rieng/ho-so-visa/a.pdf?expires=', Storage::disk('rieng')->temporaryUrl('ho-so-visa/a.pdf', now()->addMinutes(5)));
    }

    public function test_phan_quyen(): void
    {
        // Nhân viên sale không có quyền visa
        $sale = User::factory()->create();
        $sale->givePermissionTo(Permission::findOrCreate('ViewAny:Booking', 'web'));
        $this->actingAs($sale);
        $this->get('/admin/visa-cases')->assertForbidden();
        $this->get('/admin/visa-checklists')->assertForbidden();

        // Nhân viên visa vào được hồ sơ visa nhưng không đụng tour
        $this->actingAs($this->nhanVienVisa());
        $this->get('/admin/visa-cases')->assertOk();
        $this->get('/admin/visa-checklists')->assertOk();
        $this->get('/admin/tours')->assertForbidden();

        // super_admin / admin cũng có quyền visa (máy mới cài: RoleSeeder cấp)
        $this->seed(RoleSeeder::class);
        $this->assertTrue(Role::findByName('admin', 'web')->hasPermissionTo('ViewAny:VisaCase'));
    }
}
