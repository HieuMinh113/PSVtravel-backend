<?php

namespace Tests\Feature;

use App\Filament\Resources\VisaCases\Pages\CreateVisaCase;
use App\Filament\Resources\VisaCases\Pages\EditVisaCase;
use App\Filament\Resources\VisaCases\Pages\ListVisaCases;
use App\Filament\Resources\VisaCases\VisaCaseResource;
use App\Filament\Resources\VisaChecklists\Pages\ListVisaChecklists;
use App\Mail\HoSoVisaMail;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Modules\Visa\Database\Seeders\MauChecklistVisaSeeder;
use Modules\Visa\Models\VisaCase;
use Modules\Visa\Models\VisaChecklist;
use Modules\Visa\Models\VisaCountry;
use Modules\Visa\Services\XuatHoSoVisa;
use Modules\Visa\Services\XuatMauChecklist;
use Modules\Visa\Services\XuatTam;
use PhpOffice\PhpSpreadsheet\IOFactory;
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

        $this->assertSame(46, VisaChecklist::count());
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
        // Mẫu ghi rõ đối tượng được ưu tiên; không có thì dùng mẫu chung của nước
        $this->assertSame('Hàn Quốc — Đoàn du lịch — Nhân viên', VisaChecklist::timMau('Hàn Quốc', 'du_lich', 'nhan_vien')?->name);
        $this->assertSame('Hàn Quốc — Du lịch', VisaChecklist::timMau('Hàn Quốc', 'du_lich', 'tu_do')?->name);
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

    // ------------------------------------------------------------------
    // Chỉ thấy hồ sơ của mình + nhận hồ sơ + khách nộp trên web
    // ------------------------------------------------------------------

    private function quanTri(): User
    {
        $this->seed(RoleSeeder::class);
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    private function nuocTrungQuoc(): VisaCountry
    {
        return VisaCountry::create(['name' => 'Trung Quốc', 'slug' => 'trung-quoc', 'status' => 'published', 'price' => 1500000]);
    }

    private function nopWeb(array $them = []): TestResponse
    {
        return $this->postJson('/api/v1/visa-applications', [
            'visa_country' => 'trung-quoc',
            'full_name' => 'Pham Thi Mai',
            'phone' => '0909 111 222',
            'email' => 'mai@example.com',
            'purpose' => 'du_lich',
            'profile' => 'nhan_vien',
            'travel_date' => '2026-12-01',
            'note' => 'Đi cùng chồng',
            'dong_y' => true,
            ...$them,
        ]);
    }

    public function test_nhan_vien_chi_thay_ho_so_cua_minh_va_ho_so_chua_ai_nhan(): void
    {
        $a = $this->nhanVienVisa();
        $b = $this->nhanVienVisa();
        $cuaA = VisaCase::create(['full_name' => 'KHACH A', 'country' => 'Ai Cập', 'assigned_to' => $a->id]);
        $cuaB = VisaCase::create(['full_name' => 'KHACH B', 'country' => 'Ai Cập', 'assigned_to' => $b->id]);
        $trong = tap((new VisaCase)->forceFill(['full_name' => 'KHACH WEB', 'country' => 'Ai Cập', 'source' => 'website']))->save();

        $this->actingAs($a);
        Livewire::test(ListVisaCases::class)
            ->set('activeTab', 'tat_ca')
            ->assertCanSeeTableRecords([$cuaA, $trong])
            ->assertCanNotSeeTableRecords([$cuaB])
            ->set('activeTab', 'chua_nhan')
            ->assertCanSeeTableRecords([$trong])
            ->assertCanNotSeeTableRecords([$cuaA]);

        $this->get(VisaCaseResource::getUrl('edit', ['record' => $cuaB]))->assertNotFound();
        // Hồ sơ chưa ai nhận: thấy trong danh sách nhưng chưa sửa được
        $this->get(VisaCaseResource::getUrl('edit', ['record' => $trong]))->assertForbidden();
        $this->assertSame('1', VisaCaseResource::getNavigationBadge());

        // Admin thấy hết
        $this->actingAs($this->quanTri());
        Livewire::test(ListVisaCases::class)
            ->set('activeTab', 'tat_ca')
            ->assertCanSeeTableRecords([$cuaA, $cuaB, $trong]);
    }

    public function test_nhan_ho_so_ai_bam_truoc_duoc_truoc(): void
    {
        $a = $this->nhanVienVisa();
        $b = $this->nhanVienVisa();
        $trong = tap((new VisaCase)->forceFill(['full_name' => 'KHACH WEB', 'country' => 'Ai Cập', 'source' => 'website']))->save();

        $this->actingAs($a);
        Livewire::test(ListVisaCases::class)
            ->set('activeTab', 'chua_nhan')
            ->callTableAction('nhanHoSo', $trong)
            ->assertRedirect(VisaCaseResource::getUrl('edit', ['record' => $trong]));
        $this->assertSame($a->id, $trong->fresh()->assigned_to);

        // B bấm sau: hồ sơ đã có chủ → không nhận được, cũng không còn thấy
        $this->assertFalse($trong->fresh()->nhanBoi($b));
        $this->assertFalse($b->can('nhan', $trong->fresh()));
        $this->actingAs($b);
        Livewire::test(ListVisaCases::class)->set('activeTab', 'tat_ca')->assertCanNotSeeTableRecords([$trong]);
    }

    public function test_nhan_vien_khong_chuyen_duoc_ho_so_admin_thi_duoc(): void
    {
        $a = $this->nhanVienVisa();
        $b = $this->nhanVienVisa();
        $hs = VisaCase::create(['full_name' => 'KHACH A', 'country' => 'Ai Cập', 'assigned_to' => $a->id]);

        $this->actingAs($a);
        Livewire::test(EditVisaCase::class, ['record' => $hs->getRouteKey()])
            ->assertFormFieldIsDisabled('assigned_to')
            ->fillForm(['assigned_to' => $b->id, 'note' => 'đã gọi khách'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame($a->id, $hs->fresh()->assigned_to);
        $this->assertSame('đã gọi khách', $hs->fresh()->note);

        // Nhân viên tạo hồ sơ → luôn là của chính mình
        Livewire::test(CreateVisaCase::class)
            ->fillForm(['full_name' => 'KHACH MOI', 'country' => 'Ai Cập', 'assigned_to' => $b->id])
            ->call('create')
            ->assertHasNoFormErrors();
        $this->assertSame($a->id, VisaCase::where('full_name', 'KHACH MOI')->value('assigned_to'));

        $this->actingAs($this->quanTri());
        Livewire::test(EditVisaCase::class, ['record' => $hs->getRouteKey()])
            ->assertFormFieldIsEnabled('assigned_to')
            ->fillForm(['assigned_to' => $b->id])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame($b->id, $hs->fresh()->assigned_to);
    }

    public function test_khach_nop_ho_so_tren_web_bao_chuong_va_gui_mail(): void
    {
        Mail::fake();
        $this->nuocTrungQuoc();
        $a = $this->nhanVienVisa();
        $sale = User::factory()->create();

        $tl = $this->nopWeb()->assertCreated()
            ->assertJsonPath('data.max_files', 10)
            ->assertJsonPath('data.documents.0', 'Hộ chiếu bản gốc');

        $hs = VisaCase::firstOrFail();
        $this->assertSame($tl->json('data.code'), $hs->code);
        $this->assertSame('website', $hs->source);
        $this->assertNull($hs->assigned_to);
        $this->assertNull($hs->user_id);
        $this->assertSame('PHAM THI MAI', $hs->full_name);
        $this->assertSame('Trung Quốc', $hs->country);
        $this->assertSame('Trung Quốc — Du lịch — Nhân viên', $hs->mauChecklist->name);
        $this->assertSame('Đi cùng chồng', $hs->customer_note);
        $this->assertNull($hs->note);

        $this->assertSame(1, $a->notifications()->count());
        $this->assertSame(0, $sale->notifications()->count());
        Mail::assertQueued(HoSoVisaMail::class, fn ($m) => $m->hasTo('mai@example.com') && $m->hoSo->is($hs));
        $this->assertStringContainsString($hs->code, (new HoSoVisaMail($hs))->render());

        // Bấm gửi lần hai → cùng hồ sơ, không tạo trùng
        $this->nopWeb()->assertOk()->assertJsonPath('data.code', $hs->code);
        $this->assertSame(1, VisaCase::count());
    }

    public function test_nop_web_kiem_tra_du_lieu_va_chan_bot(): void
    {
        $this->nuocTrungQuoc();
        VisaCountry::create(['name' => 'Ẩn', 'slug' => 'an', 'status' => 'hidden']);

        $this->nopWeb(['phone' => 'abc', 'dong_y' => false, 'visa_country' => 'an'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone', 'dong_y', 'visa_country']);
        $this->nopWeb(['website' => 'http://spam'])->assertUnprocessable();
        $this->assertSame(0, VisaCase::count());
    }

    public function test_khach_gui_tung_file_toi_da_10_bang_ma_tai(): void
    {
        Storage::fake('rieng');
        $this->nuocTrungQuoc();
        $tl = $this->nopWeb()->assertCreated();
        $ma = $tl->json('data.code');
        $token = $tl->json('data.upload_token');
        $gui = fn (array $them) => $this->post("/api/v1/visa-applications/{$ma}/files", $them, ['Accept' => 'application/json']);

        $gui(['token' => $token, 'file' => UploadedFile::fake()->create('ho chieu.pdf', 500, 'application/pdf')])->assertCreated();
        $gui(['token' => 'sai', 'file' => UploadedFile::fake()->image('a.jpg')])->assertForbidden();
        $gui(['token' => $token, 'file' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')])->assertUnprocessable();
        $gui(['token' => $token, 'file' => UploadedFile::fake()->create('to.pdf', 11 * 1024, 'application/pdf')])->assertUnprocessable();

        $hs = VisaCase::where('code', $ma)->first();
        $this->assertCount(1, $hs->files);
        $this->assertSame('ho chieu.pdf', $hs->file_names[$hs->files[0]]);
        Storage::disk('rieng')->assertExists($hs->files[0]);

        for ($i = 2; $i <= 10; $i++) {
            $gui(['token' => $token, 'file' => UploadedFile::fake()->image("anh{$i}.png")])->assertCreated();
        }
        $gui(['token' => $token, 'file' => UploadedFile::fake()->image('anh11.png')])->assertUnprocessable();
        $this->assertCount(10, $hs->fresh()->files);

        // Hết 2 giờ → mã tải file hết hạn
        $this->travel(3)->hours();
        $gui(['token' => $token, 'file' => UploadedFile::fake()->image('muon.png')])->assertForbidden();
    }

    public function test_khach_dang_nhap_xem_ho_so_cua_minh_o_tai_khoan(): void
    {
        $this->nuocTrungQuoc();
        $khach = User::factory()->create();
        $nguoiKhac = User::factory()->create();
        Sanctum::actingAs($khach);

        $this->nopWeb()->assertCreated();
        $hs = VisaCase::firstOrFail();
        $this->assertSame($khach->id, $hs->user_id);
        $hs->update(['status' => 'dang_gom', 'note' => 'GHI CHU NOI BO', 'cost' => 999000,
            'checklist' => [['ten' => 'Hộ chiếu bản gốc', 'trang_thai' => 'da_nhan'], ['ten' => 'CCCD photo', 'trang_thai' => 'thieu']]]);
        tap((new VisaCase)->forceFill(['full_name' => 'NGUOI KHAC', 'country' => 'Ai Cập', 'user_id' => $nguoiKhac->id]))->save();

        $tl = $this->getJson('/api/v1/auth/visa-cases')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', $hs->code)
            ->assertJsonPath('data.0.status_label', 'Đang gom giấy tờ')
            ->assertJsonPath('data.0.documents_received', 1)
            ->assertJsonPath('data.0.missing_documents', ['CCCD photo']);
        $this->assertStringNotContainsString('GHI CHU NOI BO', $tl->getContent());
        $this->assertStringNotContainsString('999000', $tl->getContent());

        // Chưa đăng nhập thì không xem được
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/auth/visa-cases')->assertUnauthorized();
    }

    // ------------------------------------------------------------------
    // File theo từng giấy tờ + phiếu thông tin + xuất ZIP
    // ------------------------------------------------------------------

    /** Hồ sơ có sẵn file trên ổ riêng (đã Storage::fake). */
    private function hoSoCoFile(array $them = []): VisaCase
    {
        $dia = Storage::disk('rieng');
        $dia->put('ho-so-visa/hc1.pdf', '%PDF hộ chiếu trang 1');
        $dia->put('ho-so-visa/hc2.pdf', '%PDF hộ chiếu trang visa');
        $dia->put('ho-so-visa/anh.jpg', 'JPG');
        $dia->put('ho-so-visa/khac.png', 'PNG');

        return VisaCase::create([
            'full_name' => 'NGUYỄN TÔ THỤY BẢO CHÂU', 'country' => 'Hàn Quốc', 'purpose' => 'du_lich',
            'birth_date' => '1990-05-01', 'passport_no' => '012345678', 'phone' => '0909000111',
            'group_name' => 'gđ anh tuấn',
            'checklist' => [
                ['nhom' => 'Hồ sơ nhân thân', 'ten' => 'Hộ chiếu', 'trang_thai' => 'da_nhan', 'tep' => ['ho-so-visa/hc1.pdf', 'ho-so-visa/hc2.pdf']],
                ['nhom' => 'Hồ sơ nhân thân', 'ten' => 'Ảnh thẻ 3.5x4.5', 'trang_thai' => 'thieu', 'tep' => ['ho-so-visa/anh.jpg']],
                ['nhom' => 'Hồ sơ tài chính', 'ten' => 'Sao kê ngân hàng', 'trang_thai' => 'thieu'],
                ['nhom' => 'Hồ sơ tài chính', 'ten' => 'Sổ tiết kiệm', 'trang_thai' => 'khong_can'],
            ],
            'files' => ['ho-so-visa/khac.png'],
            'file_names' => ['ho-so-visa/khac.png' => 'zalo 123.png'],
            'thong_tin' => ['noi_sinh' => 'Hà Nội', 'hon_nhan' => 'da_ket_hon', 'so_cccd' => '001090000111', 'nghe_nghiep' => 'Kế toán'],
            ...$them,
        ]);
    }

    private function mucTrongZip(string $duong): array
    {
        $zip = new \ZipArchive;
        $zip->open($duong);
        $ds = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $ds[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
        }
        $zip->close();

        return $ds;
    }

    public function test_giay_co_file_tu_thanh_da_nhan(): void
    {
        Storage::fake('rieng');
        $hs = $this->hoSoCoFile();

        // Dòng "Ảnh thẻ" ghi Chưa có nhưng đã có file → tự thành Đã nhận
        $this->assertSame('da_nhan', $hs->fresh()->checklist[1]['trang_thai']);
        $this->assertSame(['Sao kê ngân hàng'], $hs->fresh()->giayConThieu());
        $this->assertCount(4, $hs->tatCaFile());

        $this->actingAs($this->quanTri());
        Livewire::test(EditVisaCase::class, ['record' => $hs->getRouteKey()])->assertOk()->assertSee('Đã nhận 2/3 giấy tờ');

        $hs->forceDelete();
        Storage::disk('rieng')->assertMissing(['ho-so-visa/hc1.pdf', 'ho-so-visa/anh.jpg', 'ho-so-visa/khac.png']);
    }

    public function test_xuat_zip_mot_nguoi_dat_ten_theo_giay_to(): void
    {
        Storage::fake('rieng');
        $hs = $this->hoSoCoFile();

        [$duong, $ten] = app(XuatHoSoVisa::class)->motNguoi($hs->fresh());
        $this->assertSame('NGUYỄN TÔ THỤY BẢO CHÂU.zip', $ten);

        $muc = $this->mucTrongZip($duong);
        $thuMuc = 'NGUYỄN TÔ THỤY BẢO CHÂU/';
        $this->assertSame('%PDF hộ chiếu trang 1', $muc[$thuMuc.'Hộ chiếu (1).pdf']);
        $this->assertSame('%PDF hộ chiếu trang visa', $muc[$thuMuc.'Hộ chiếu (2).pdf']);
        $this->assertSame('JPG', $muc[$thuMuc.'Ảnh thẻ 3.5x4.5.jpg']);
        $this->assertSame('PNG', $muc[$thuMuc.'Giấy tờ khác/zalo 123.png']);
        $this->assertStringContainsString('1. Sao kê ngân hàng', $muc[$thuMuc.'GIẤY TỜ CÒN THIẾU.txt']);
        $this->assertStringNotContainsString('Sổ tiết kiệm', $muc[$thuMuc.'GIẤY TỜ CÒN THIẾU.txt']);

        // Phiếu thông tin: file Word có câu trả lời của khách
        $tamDocx = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($tamDocx, $muc[$thuMuc.'Phiếu thông tin.docx']);
        $docx = new \ZipArchive;
        $docx->open($tamDocx);
        $xml = $docx->getFromName('word/document.xml');
        $this->assertStringContainsString('PHIẾU THÔNG TIN XIN VISA HÀN QUỐC', $xml);
        $this->assertStringContainsString('Hà Nội', $xml);
        $this->assertStringContainsString('Đã kết hôn', $xml);
        $this->assertStringContainsString('012345678', $xml);
        @unlink($tamDocx);
        @unlink($duong);

        // Đủ giấy → không có file GIẤY TỜ CÒN THIẾU
        $hs->update(['checklist' => [['ten' => 'Hộ chiếu', 'trang_thai' => 'da_nhan', 'tep' => ['ho-so-visa/hc1.pdf']]]]);
        [$duong] = app(XuatHoSoVisa::class)->motNguoi($hs->fresh());
        $this->assertArrayNotHasKey($thuMuc.'GIẤY TỜ CÒN THIẾU.txt', $this->mucTrongZip($duong));
        @unlink($duong);
    }

    public function test_xuat_zip_ca_doan_co_danh_sach_excel(): void
    {
        Storage::fake('rieng');
        $chau = $this->hoSoCoFile();
        $tuan = $this->hoSoCoFile(['full_name' => 'NGUYỄN DUY TUẤN', 'passport_no' => '0011', 'thong_tin' => ['gioi_tinh' => 'nam']]);
        $trungTen = $this->hoSoCoFile(['full_name' => 'NGUYỄN DUY TUẤN', 'passport_no' => '0022']);

        [$duong, $ten] = app(XuatHoSoVisa::class)->caDoan(collect([$chau, $tuan, $trungTen])->map->fresh());
        $this->assertSame('gđ anh tuấn.zip', $ten);
        $muc = $this->mucTrongZip($duong);

        $this->assertArrayHasKey('NGUYỄN TÔ THỤY BẢO CHÂU/Hộ chiếu (1).pdf', $muc);
        $this->assertArrayHasKey('NGUYỄN DUY TUẤN/Phiếu thông tin.docx', $muc);
        $this->assertArrayHasKey("NGUYỄN DUY TUẤN - {$trungTen->code}/Phiếu thông tin.docx", $muc);

        $tam = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($tam, $muc['DANH SÁCH gđ anh tuấn.xlsx']);
        $bang = IOFactory::load($tam)->getActiveSheet();
        $this->assertSame('Họ và tên', $bang->getCell('B1')->getValue());
        $this->assertSame('NGUYỄN DUY TUẤN', $bang->getCell('B3')->getValue());
        $this->assertSame('Nam', $bang->getCell('C3')->getValue());
        $this->assertSame('0011', $bang->getCell('F3')->getValue()); // giữ số 0 đầu
        $this->assertSame('Hà Nội', $bang->getCell('E2')->getValue());
        $this->assertSame('Sao kê ngân hàng', $bang->getCell('U2')->getValue());
        @unlink($tam);
        @unlink($duong);
    }

    public function test_nut_xuat_zip_tai_bang_link_chi_nguoi_xuat_dung_duoc(): void
    {
        Storage::fake('rieng');
        $nv = $this->nhanVienVisa();
        $hs = $this->hoSoCoFile(['assigned_to' => $nv->id]);
        $this->actingAs($nv);

        $tl = Livewire::test(EditVisaCase::class, ['record' => $hs->getRouteKey()])
            ->assertActionVisible('xuatZip')
            ->callAction('xuatZip');
        $link = $tl->effects['redirect'] ?? null;
        $this->assertStringContainsString('/quan-tri/ho-so-visa/tai-zip/', (string) $link);
        $this->assertDatabaseHas('activity_log', ['description' => 'Xuất hồ sơ ZIP', 'subject_id' => $hs->id]);

        // Người khác cầm link → không tải được
        $this->actingAs($this->nhanVienVisa());
        $this->get($link)->assertForbidden();

        // Đúng người → tải được, tải xong file tạm bị xoá
        $this->actingAs($nv);
        $tai = $this->get($link)->assertOk()->assertDownload();
        $this->assertStringContainsString(rawurlencode('NGUYỄN TÔ THỤY BẢO CHÂU.zip'), $tai->headers->get('content-disposition'));
        ob_start();
        $tai->baseResponse->sendContent(); // gửi xong mới xoá (như máy chủ thật)
        ob_end_clean();
        $this->assertSame([], Storage::disk('rieng')->files('xuat-tam'));
        $this->get($link)->assertNotFound();

        // Link bị sửa → chữ ký sai
        $this->get($link.'x')->assertForbidden();
    }

    public function test_xuat_zip_doan_bo_qua_ho_so_khong_thuoc_quyen(): void
    {
        Storage::fake('rieng');
        $nv = $this->nhanVienVisa();
        $cuaToi = $this->hoSoCoFile(['assigned_to' => $nv->id]);
        $trong = tap((new VisaCase)->forceFill(['full_name' => 'KHACH WEB', 'country' => 'Hàn Quốc', 'source' => 'website', 'group_name' => 'gđ anh tuấn']))->save();
        $this->actingAs($nv);

        Livewire::test(ListVisaCases::class)
            ->set('activeTab', 'tat_ca')
            ->callTableBulkAction('xuatZipDoan', [$cuaToi, $trong])
            ->assertNotified('Bỏ qua 1 hồ sơ không thuộc quyền của bạn.');

        $tep = Storage::disk('rieng')->files('xuat-tam');
        $this->assertCount(1, $tep);
        $muc = $this->mucTrongZip(Storage::disk('rieng')->path($tep[0]));
        $this->assertArrayHasKey('NGUYỄN TÔ THỤY BẢO CHÂU/Hộ chiếu (1).pdf', $muc);
        $this->assertArrayNotHasKey('KHACH WEB/Phiếu thông tin.docx', $muc);

        // Dọn file tạm cũ hơn 1 ngày (chưa ai tải)
        $this->assertSame(0, XuatTam::donDep());
        touch(Storage::disk('rieng')->path($tep[0]), now()->subDays(2)->getTimestamp());
        $this->assertSame(1, XuatTam::donDep());
    }

    public function test_web_lay_danh_sach_giay_va_tai_file_vao_dung_giay(): void
    {
        Storage::fake('rieng');
        $this->nuocTrungQuoc();

        $this->getJson('/api/v1/visa-applications/checklist?visa_country=trung-quoc&purpose=du_lich&profile=nhan_vien')
            ->assertOk()
            ->assertJsonPath('data.items.0.ten', 'Hộ chiếu bản gốc')
            ->assertJsonPath('data.items.5.ten', 'Xác nhận việc làm')
            ->assertJsonPath('data.items.5.muc', 5);
        $this->getJson('/api/v1/visa-applications/checklist?visa_country=khong-co')->assertOk()->assertJsonCount(0, 'data.items');
        $this->getJson('/api/v1/visa-applications/phieu')->assertOk()
            ->assertJsonPath('data.0.tieu_de', 'Nhân thân')
            ->assertJsonPath('data.0.cau_hoi.3.lua_chon.da_ket_hon', 'Đã kết hôn');

        $tl = $this->nopWeb(['thong_tin' => [
            'noi_sinh' => 'Cần Thơ', 'hon_nhan' => 'doc_than', 'gioi_tinh' => 'khong-hop-le',
            'khoa_la' => 'x', 'da_den_nuoc_nay' => 'co',
        ]])->assertCreated();
        $hs = VisaCase::firstOrFail();
        $this->assertSame(['noi_sinh' => 'Cần Thơ', 'hon_nhan' => 'doc_than', 'da_den_nuoc_nay' => 'co'], $hs->thong_tin);

        $gui = fn (array $them) => $this->post('/api/v1/visa-applications/'.$hs->code.'/files',
            ['token' => $tl->json('data.upload_token'), ...$them], ['Accept' => 'application/json']);
        $gui(['muc' => 0, 'file' => UploadedFile::fake()->create('scan hc.pdf', 100, 'application/pdf')])->assertCreated();
        $gui(['muc' => 99, 'file' => UploadedFile::fake()->image('la.png')])->assertCreated();
        $gui(['file' => UploadedFile::fake()->image('khac.png')])->assertCreated();

        $hs->refresh();
        $this->assertSame('da_nhan', $hs->checklist[0]['trang_thai']);
        $this->assertCount(1, $hs->checklist[0]['tep']);
        $this->assertSame('scan hc.pdf', $hs->checklist[0]['ten_tep'][$hs->checklist[0]['tep'][0]]);
        $this->assertCount(2, $hs->files); // muc sai + không chọn mục → Giấy tờ khác
        $this->assertSame(3, $hs->web_files_count);
    }

    public function test_migration_them_mau_doan_han(): void
    {
        $mau = VisaChecklist::timMau('Hàn Quốc', 'du_lich', 'nhan_vien');
        $this->assertSame('Hàn Quốc — Đoàn du lịch — Nhân viên', $mau->name);
        $this->assertSame('VssID', collect($mau->items)->firstWhere('nhom', 'Hồ sơ nghề nghiệp') ? collect($mau->items)->where('nhom', 'Hồ sơ nghề nghiệp')->last()['ten'] : null);

        // Chạy lại không thêm trùng
        MauChecklistVisaSeeder::themMauNeuChuaCo(MauChecklistVisaSeeder::mauDoanHan());
        $this->assertSame(46, VisaChecklist::count());
    }

    public function test_mau_17_nuoc_chi_chep_giay_cua_dung_doi_tuong(): void
    {
        $nhat = $this->mau('Nhật Bản — Du lịch');
        $this->assertSame(['mau-visa/nhat-ban-thu-tuc.pdf', 'mau-visa/nhat-ban-phieu-thong-tin.docx'], $nhat->attachments);
        $this->assertSame('Phiếu thông tin xin visa Nhật Bản.docx', $nhat->attachment_names['mau-visa/nhat-ban-phieu-thong-tin.docx']);
        $this->assertTrue(Storage::disk('rieng')->exists('mau-visa/nhat-ban-thu-tuc.pdf'));

        $ten = fn (?string $doiTuong) => collect(VisaCase::chepMau($nhat, $doiTuong))->pluck('ten');
        $this->assertContains('Hợp đồng lao động hoặc quyết định bổ nhiệm', $ten('nhan_vien'));
        $this->assertNotContains('Giấy phép kinh doanh', $ten('nhan_vien'));
        $this->assertContains('Giấy phép kinh doanh', $ten('chu_doanh_nghiep'));
        $this->assertNotContains('Sao kê lương 6 tháng gần nhất', $ten('chu_doanh_nghiep'));
        $this->assertContains('Thuế 3 tháng gần nhất', $ten('ho_kinh_doanh'));
        $this->assertContains('Hộ chiếu', $ten('huu_tri'));
        $this->assertCount(count($nhat->items), $ten(null)); // chưa biết đối tượng → chép hết

        // Nước có 3 mục đích: mẫu thăm thân có thêm phần người mời
        $this->assertSame('Đức — Thăm thân', VisaChecklist::timMau('Đức', 'tham_than', 'nhan_vien')->name);
        $this->assertContains('Giấy bảo lãnh của Tòa Thị chính nơi người mời cư trú', collect($this->mau('Đức — Thăm thân')->items)->pluck('ten'));
        $this->assertNull(VisaChecklist::timMau('Ba Lan', 'cong_tac', 'nhan_vien')); // file Ba Lan không có công tác

        // Web: danh sách giấy theo đối tượng
        VisaCountry::create(['name' => 'Nhật Bản', 'slug' => 'nhat-ban', 'status' => 'published']);
        $this->getJson('/api/v1/visa-applications/checklist?visa_country=nhat-ban&purpose=du_lich&profile=chu_doanh_nghiep')
            ->assertOk()
            ->assertJsonFragment(['ten' => 'Giấy phép kinh doanh'])
            ->assertJsonMissing(['ten' => 'Bảo hiểm xã hội']);
    }

    public function test_xuat_mau_checklist_word_pdf_kem_file_mau(): void
    {
        $mau = $this->mau('Nhật Bản — Du lịch');
        $this->assertNotEmpty($mau->attachments);
        $mau->update(['note' => 'Nộp trước ngày đi 15 ngày.']);

        [$tam, $ten] = app(XuatMauChecklist::class)->zip(collect([$mau]), 'ca_hai');
        $this->assertSame('Checklist - Nhật Bản — Du lịch.zip', $ten);
        $muc = $this->mucTrongZip($tam);
        $this->assertArrayHasKey('Danh sách giấy tờ - Nhật Bản — Du lịch.docx', $muc);
        $this->assertStringStartsWith('%PDF', $muc['Danh sách giấy tờ - Nhật Bản — Du lịch.pdf']);
        $this->assertNotEmpty(array_filter(array_keys($muc), fn ($k) => str_starts_with($k, 'File mẫu/')));

        // Nội dung Word: tiêu đề, giấy tờ đầu tiên, lưu ý
        $docx = tempnam(sys_get_temp_dir(), 't');
        file_put_contents($docx, $muc['Danh sách giấy tờ - Nhật Bản — Du lịch.docx']);
        $xml = $this->mucTrongZip($docx)['word/document.xml'];
        $this->assertStringContainsString('DANH SÁCH GIẤY TỜ XIN VISA NHẬT BẢN', $xml);
        $this->assertStringContainsString(htmlspecialchars($mau->items[0]['ten'], ENT_XML1), $xml);
        $this->assertStringContainsString('Nộp trước ngày đi 15 ngày.', $xml);

        // Nhiều mẫu → mỗi mẫu một thư mục, chỉ Word
        [$tam2, $ten2] = app(XuatMauChecklist::class)
            ->zip(collect([$mau, $this->mau('Ai Cập — Du lịch')]), 'word');
        $this->assertSame('Checklist visa - 2 mẫu.zip', $ten2);
        $muc2 = array_keys($this->mucTrongZip($tam2));
        $this->assertContains('Ai Cập — Du lịch/Danh sách giấy tờ - Ai Cập — Du lịch.docx', $muc2);
        $this->assertEmpty(array_filter($muc2, fn ($k) => str_contains($k, 'Danh sách giấy tờ') && str_ends_with($k, '.pdf')));

        // Nút trong trang quản trị → chuyển sang link tải có chữ ký
        $this->actingAs($this->nhanVienVisa());
        Livewire::test(ListVisaChecklists::class)
            ->callTableAction('xuatFile', $mau, data: ['dinh_dang' => 'pdf'])
            ->assertHasNoTableActionErrors()
            ->assertRedirectContains('/tai-zip/');
    }
}
