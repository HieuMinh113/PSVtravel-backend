<?php

namespace Tests\Feature;

use App\Filament\Pages\BangCongThang;
use App\Filament\Pages\CauHinhChamCong;
use App\Filament\Pages\ChamCong;
use App\Filament\Resources\AttendanceFaces\Pages\ListAttendanceFaces;
use App\Filament\Resources\Attendances\Pages\ListAttendances;
use App\Models\Attendance;
use App\Models\AttendanceFace;
use App\Models\User;
use App\Services\ChamCong\ChamCong as DichVu;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Page\Models\Setting;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Chấm công: đăng ký khuôn mặt (có đồng ý), chấm vào / ra với khuôn mặt +
 * vị trí, lý do khi trễ / về sớm / ngoài công ty, bổ sung công, quản lý duyệt,
 * bảng công tháng, xoá ảnh sau 3 tháng.
 */
class ChamCongTest extends TestCase
{
    use RefreshDatabase;

    private const LAT = 10.74;

    private const LNG = 106.73;

    private array $mat;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('rieng');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        // Thứ 2, 12/10/2026
        $this->travelTo(Carbon::parse('2026-10-12 07:50'));
        Setting::updateOrCreate(['key' => 'cham_cong_lat'], ['value' => (string) self::LAT, 'group' => 'cham_cong']);
        Setting::updateOrCreate(['key' => 'cham_cong_lng'], ['value' => (string) self::LNG, 'group' => 'cham_cong']);
        $this->mat = array_map(fn ($i) => round(sin($i) / 10, 6), range(1, 128));
    }

    private function nhanVien(string $ten = 'Lan'): User
    {
        $vaiTro = Role::findOrCreate('sale', 'web');
        $vaiTro->givePermissionTo(Permission::findOrCreate('ViewAny:Booking', 'web'));

        return User::factory()->create(['name' => $ten])->assignRole($vaiTro);
    }

    private function quanLy(): User
    {
        return $this->nhanVien('Quản lý')->givePermissionTo('ViewAll:Attendance');
    }

    private function anh(): string
    {
        $a = imagecreatetruecolor(64, 48);
        imagefill($a, 0, 0, imagecolorallocate($a, 200, 180, 160));
        ob_start();
        imagejpeg($a, null, 90);

        return 'data:image/jpeg;base64,'.base64_encode(ob_get_clean());
    }

    /** Mặt khác người: lệch mỗi số 0,08 → khoảng cách ≈ 0,9. */
    private function matKhac(): array
    {
        return array_map(fn ($v) => $v + 0.08, $this->mat);
    }

    private function dangKy(User $u): void
    {
        app(DichVu::class)->dangKy($u, $this->mat, $this->anh());
    }

    private function cham(User $u, string $loai, array $them = []): array
    {
        return app(DichVu::class)->cham($u, $loai, [
            'lat' => self::LAT + 0.0003, 'lng' => self::LNG, 'accuracy' => 15,
            'anh' => $this->anh(), 'descriptor' => $this->mat, ...$them,
        ]);
    }

    public function test_migration_tao_cau_hinh_va_quyen_quan_ly(): void
    {
        $this->assertSame('150', Setting::where('key', 'cham_cong_ban_kinh')->value('value'));
        $this->assertSame('12:00', Setting::where('key', 'cham_cong_ra_t7')->value('value'));
        $this->seed(RoleSeeder::class);
        $this->assertTrue(Role::findByName('admin', 'web')->hasPermissionTo('ViewAll:Attendance'));
    }

    public function test_dang_ky_khuon_mat_can_dong_y_va_chi_mot_lan(): void
    {
        $u = $this->nhanVien();
        $this->actingAs($u);

        $trang = Livewire::test(ChamCong::class)->assertOk()->assertSee('Đăng ký khuôn mặt');
        $this->assertFalse($trang->instance()->dangKyKhuonMat($this->mat, $this->anh(), false)['ok']);
        $this->assertFalse($trang->instance()->dangKyKhuonMat(null, $this->anh(), true)['ok']); // không thấy mặt
        $this->assertFalse($trang->instance()->dangKyKhuonMat($this->mat, 'data:image/png;base64,AAAA', true)['ok']);
        $this->assertTrue($trang->instance()->dangKyKhuonMat($this->mat, $this->anh(), true)['ok']);

        $f = AttendanceFace::where('user_id', $u->id)->sole();
        $this->assertNotNull($f->consented_at);
        Storage::disk('rieng')->assertExists($f->photo);
        $this->assertStringContainsString('đã đăng ký', $trang->instance()->dangKyKhuonMat($this->mat, $this->anh(), true)['loi']);

        // Chưa đăng ký thì không chấm được
        $this->assertFalse($this->cham($this->nhanVien('Hà'), 'vao')['ok']);
    }

    public function test_cham_vao_dung_gio_o_cong_ty_khop_mat(): void
    {
        $u = $this->nhanVien();
        $this->dangKy($u);

        $kq = $this->cham($u, 'vao');
        $this->assertTrue($kq['ok'], json_encode($kq));
        $this->assertSame([], $kq['canh_bao']);
        $a = Attendance::sole();
        $this->assertSame('2026-10-12', $a->work_date->toDateString());
        $this->assertSame('07:50', $a->in_at->format('H:i'));
        $this->assertSame('trong', $a->in_location);
        $this->assertTrue($a->in_face_ok);
        $this->assertSame(0, $a->late_minutes);
        $this->assertNull($a->review_status);
        $this->assertLessThan(60, $a->in_distance);
        Storage::disk('rieng')->assertExists($a->in_photo);

        // Chấm vào lần 2 → báo đã chấm
        $this->assertStringContainsString('đã chấm vào lúc 07:50', $this->cham($u, 'vao')['loi']);
    }

    public function test_tre_ve_som_ngoai_cong_ty_phai_ghi_ly_do(): void
    {
        $u = $this->nhanVien();
        $this->dangKy($u);

        // 08:04 vẫn trong 5 phút cho phép
        $this->travelTo(Carbon::parse('2026-10-12 08:04'));
        $this->assertTrue($this->cham($u, 'vao')['ok']);
        Attendance::query()->delete();

        // 08:20 trễ, ở xa 2,2 km → cần lý do
        $this->travelTo(Carbon::parse('2026-10-12 08:20'));
        $xa = ['lat' => self::LAT + 0.02];
        $kq = $this->cham($u, 'vao', $xa);
        $this->assertFalse($kq['ok']);
        $this->assertTrue($kq['can_ly_do']);
        $this->assertStringContainsString('Đi trễ 20 phút', $kq['vi_sao'][0]);
        $this->assertStringContainsString('cách công ty 2,2 km', $kq['vi_sao'][1]);
        $this->assertSame(0, Attendance::count());

        $this->assertTrue($this->cham($u, 'vao', [...$xa, 'ly_do' => 'Đi gặp khách đoàn Hàn ở Q1'])['ok']);
        $a = Attendance::sole();
        $this->assertSame(20, $a->late_minutes);
        $this->assertSame('ngoai', $a->in_location);
        $this->assertSame('cho_duyet', $a->review_status);
        $this->assertSame('Đi gặp khách đoàn Hàn ở Q1', $a->in_reason);

        // Về sớm 16:30 → cần lý do
        $this->travelTo(Carbon::parse('2026-10-12 16:30'));
        $this->assertTrue($this->cham($u, 'ra')['can_ly_do']);
        $this->assertTrue($this->cham($u, 'ra', ['ly_do' => 'Đưa con đi khám'])['ok']);
        $this->assertSame(60, $a->fresh()->early_minutes);

        // Không cho định vị → không rõ vị trí, phải ghi lý do
        $v = $this->nhanVien('Vy');
        $this->dangKy($v);
        $kq = $this->cham($v, 'vao', ['lat' => null, 'lng' => null]);
        $this->assertSame(['Đi trễ 8 giờ 30 phút (giờ vào 08:00)', 'Không lấy được vị trí'], $kq['vi_sao']);
    }

    public function test_dung_gio_ngoai_cong_ty_ghi_ly_do_nhung_khong_can_duyet(): void
    {
        $u = $this->nhanVien();
        $this->dangKy($u);
        $xa = ['lat' => self::LAT + 0.02];

        // 07:50 đúng giờ, ở sân bay → vẫn hỏi lý do, nhưng không chờ duyệt
        $kq = $this->cham($u, 'vao', $xa);
        $this->assertSame(['Bạn đang cách công ty 2,2 km'], $kq['vi_sao']);
        $kq = $this->cham($u, 'vao', [...$xa, 'ly_do' => 'Đón đoàn ở sân bay']);
        $this->assertStringContainsString('đã ghi lý do', $kq['thong_bao']);
        $a = Attendance::sole();
        $this->assertSame('Đón đoàn ở sân bay', $a->in_reason);
        $this->assertSame('ngoai', $a->in_location);
        $this->assertNull($a->review_status);

        // Ra đúng giờ ở công ty → vẫn không cần duyệt
        $this->travelTo(Carbon::parse('2026-10-12 17:31'));
        $this->assertTrue($this->cham($u, 'ra')['ok']);
        $this->assertNull($a->fresh()->review_status);

        // Ra lại sớm hơn (về sớm) → lúc này mới chờ duyệt
        $this->travelTo(Carbon::parse('2026-10-12 17:00'));
        $this->cham($u, 'ra', ['ly_do' => 'Đi khám bệnh']);
        $this->assertSame('cho_duyet', $a->fresh()->review_status);
    }

    public function test_khuon_mat_khong_khop_van_cham_nhung_nghi_van(): void
    {
        $u = $this->nhanVien();
        $this->dangKy($u);

        $kq = $this->cham($u, 'vao', ['descriptor' => $this->matKhac()]);
        $this->assertTrue($kq['ok']);
        $this->assertStringContainsString('chưa khớp', $kq['canh_bao'][0]);
        $a = Attendance::sole();
        $this->assertFalse($a->in_face_ok);
        $this->assertGreaterThan(Attendance::NGUONG_KHUON_MAT, $a->in_face_distance);
        $this->assertTrue($a->nghiVan());

        // Không thấy mặt (khẩu trang) → vẫn chấm ra được, nghi vấn
        $this->travelTo(Carbon::parse('2026-10-12 17:40'));
        $kq = $this->cham($u, 'ra', ['descriptor' => null]);
        $this->assertTrue($kq['ok']);
        $this->assertNull($a->fresh()->out_face_ok);
        $this->assertTrue($a->fresh()->nghiVan('out'));

        // Ảnh giả / hỏng → từ chối
        $this->assertFalse($this->cham($u, 'ra', ['anh' => 'data:image/jpeg;base64,'.base64_encode('khong phai anh')])['ok']);
    }

    public function test_quet_tu_dong_khop_moi_cham_khong_khop_thi_quet_tiep(): void
    {
        $u = $this->nhanVien();
        $this->dangKy($u);

        // Đang quét, mặt người khác → không lưu gì, không lộ khoảng cách
        $kq = $this->cham($u, 'vao', ['descriptor' => $this->matKhac(), 'chi_khi_khop' => true, 'anh' => null]);
        $this->assertSame(['ok' => false, 'khong_khop' => true], $kq);
        $kq = $this->cham($u, 'vao', ['descriptor' => null, 'chi_khi_khop' => true]);
        $this->assertTrue($kq['khong_khop']);
        $this->assertSame(0, Attendance::count());

        // Đúng mặt → chấm luôn
        $kq = $this->cham($u, 'vao', ['chi_khi_khop' => true]);
        $this->assertTrue($kq['ok']);
        $this->assertSame([], $kq['canh_bao']);
        $this->assertTrue(Attendance::sole()->in_face_ok);
        $this->assertFalse(Attendance::sole()->nghiVan());

        // Khớp nhưng cần lý do (về sớm) → hỏi lý do trước, gửi lại vẫn kiểm tra mặt
        $this->travelTo(Carbon::parse('2026-10-12 16:00'));
        $this->assertTrue($this->cham($u, 'ra', ['chi_khi_khop' => true])['can_ly_do']);
        $this->assertTrue($this->cham($u, 'ra', ['chi_khi_khop' => true, 'ly_do' => 'Đi khám'])['ok']);
        $this->assertSame('cho_duyet', Attendance::sole()->review_status);
    }

    public function test_quet_khong_khop_qua_nhieu_lan_thi_tam_khoa_nhung_van_gui_quan_ly_duoc(): void
    {
        $u = $this->nhanVien();
        $this->dangKy($u);
        $this->actingAs($u);
        $trang = Livewire::test(ChamCong::class)->instance();
        $sai = ['lat' => self::LAT, 'lng' => self::LNG, 'accuracy' => 10, 'anh' => $this->anh(), 'descriptor' => $this->matKhac(), 'chi_khi_khop' => true];

        for ($i = 0; $i < ChamCong::SO_LAN_KHONG_KHOP; $i++) {
            $this->assertTrue($trang->chamCong('vao', $sai)['khong_khop']);
        }
        $kq = $trang->chamCong('vao', $sai);
        $this->assertTrue($kq['qua_nhieu']);
        // Đúng mặt cũng phải đợi — chặn dò thử
        $this->assertTrue($trang->chamCong('vao', [...$sai, 'descriptor' => $this->mat])['qua_nhieu'] ?? false);

        // Nút "Gửi ảnh cho quản lý duyệt" vẫn chấm được, đánh dấu nghi vấn
        $kq = $trang->chamCong('vao', [...$sai, 'chi_khi_khop' => false]);
        $this->assertTrue($kq['ok']);
        $this->assertTrue(Attendance::sole()->nghiVan());
    }

    public function test_thu_7_nua_ngay_chu_nhat_khong_tinh_tre(): void
    {
        $u = $this->nhanVien();
        $this->dangKy($u);
        $this->travelTo(Carbon::parse('2026-10-17 11:00')); // thứ 7
        $this->cham($u, 'vao', ['ly_do' => 'Kẹt xe']);
        $this->travelTo(Carbon::parse('2026-10-17 12:05'));
        $this->assertSame([], $this->cham($u, 'ra')['canh_bao']);
        $t7 = Attendance::whereDate('work_date', '2026-10-17')->sole();
        $this->assertSame(180, $t7->late_minutes);
        $this->assertSame(0, $t7->early_minutes);
        $this->assertSame(0.5, $t7->cong());

        $this->travelTo(Carbon::parse('2026-10-18 14:00')); // chủ nhật
        $this->assertTrue($this->cham($u, 'vao')['ok']);
        $cn = Attendance::whereDate('work_date', '2026-10-18')->sole();
        $this->assertSame(0, $cn->late_minutes);
        $this->assertSame(0.0, (float) $cn->cong());
    }

    public function test_bo_sung_cong_cho_duyet_moi_tinh(): void
    {
        $u = $this->nhanVien();
        $this->actingAs($u);
        $this->travelTo(Carbon::parse('2026-10-14 09:00'));

        Livewire::test(ChamCong::class)
            ->callAction('boSung', data: ['ngay' => '2026-10-13', 'loai' => 'vao', 'gio' => '08:00', 'ly_do' => 'Quên chấm, điện thoại hết pin'])
            ->assertHasNoActionErrors();
        $a = Attendance::sole();
        $this->assertSame('bo_sung', $a->in_source);
        $this->assertSame('cho_duyet', $a->review_status);
        $this->assertSame(0.0, (float) $a->cong());

        // Không bổ sung ngày tương lai
        $this->expectException(\InvalidArgumentException::class);
        app(DichVu::class)->boSung($u, Carbon::parse('2026-10-20'), 'vao', '08:00', 'x');
    }

    public function test_quan_ly_duyet_xem_bang_cong_va_nhan_vien_khong_vao_duoc(): void
    {
        $lan = $this->nhanVien('Lan');
        $this->dangKy($lan);
        $this->travelTo(Carbon::parse('2026-10-12 08:30'));
        $this->cham($lan, 'vao', ['ly_do' => 'Kẹt xe cầu Kênh Tẻ']);
        $this->travelTo(Carbon::parse('2026-10-12 17:35'));
        $this->cham($lan, 'ra');
        $this->travelTo(Carbon::parse('2026-10-13 07:58'));
        $this->cham($lan, 'vao', ['descriptor' => $this->matKhac()]);
        $a = Attendance::whereDate('work_date', '2026-10-12')->sole();

        // Nhân viên: không vào được trang quản lý, vẫn vào trang chấm công
        $this->actingAs($lan);
        $this->get('/admin/bang-cham-cong')->assertForbidden();
        $this->get('/admin/bang-cong-thang')->assertForbidden();
        $this->get('/admin/cau-hinh-cham-cong')->assertForbidden();
        $this->get('/admin/khuon-mat-nhan-vien')->assertForbidden();
        $this->get('/admin/cham-cong')->assertOk();
        $this->actingAs(User::factory()->create()); // khách hàng
        $this->get('/admin/cham-cong')->assertForbidden();

        $ql = $this->quanLy();
        $this->actingAs($ql);
        $this->get('/admin/bang-cham-cong')->assertOk();
        Livewire::test(ListAttendances::class)
            ->set('activeTab', 'cho_duyet')
            ->assertCanSeeTableRecords([$a])
            ->mountTableAction('xem', $a);
        $chiTiet = view('filament.cham-cong.chi-tiet', ['a' => $a])->render();
        $this->assertStringContainsString('Mở bản đồ', $chiTiet);
        $this->assertStringContainsString('Kẹt xe cầu Kênh Tẻ', $chiTiet);
        $this->assertStringContainsString('khớp', $chiTiet);
        Livewire::test(ListAttendances::class)
            ->callTableAction('duyet', $a, data: ['ket_qua' => 'chap_nhan', 'ghi_chu' => 'OK'])
            ->assertHasNoTableActionErrors();
        $a->refresh();
        $this->assertSame('chap_nhan', $a->review_status);
        $this->assertSame($ql->id, $a->reviewed_by);

        // Bảng công tháng
        $this->travelTo(Carbon::parse('2026-10-14 09:00'));
        $trang = Livewire::test(BangCongThang::class)->assertOk()->assertCanSeeTableRecords([$lan]);
        $so = BangCongThang::cong(Attendance::where('user_id', $lan->id)->get());
        $this->assertSame(2.0, $so['cong']);
        $this->assertSame(1, $so['tre_lan']);
        $this->assertSame(30, $so['tre_phut']);
        $this->assertSame(1, $so['tre_co_phep']);
        $this->assertSame(1, $so['nghi_van']);
        $this->assertSame(1, $so['thieu_ra']); // 13/10 quên chấm ra
        $trang->callAction('xuatExcel')->assertFileDownloaded('Bang-cong-10-2026.xlsx');

        // Cho đăng ký lại khuôn mặt → xoá ảnh cũ
        $f = AttendanceFace::where('user_id', $lan->id)->sole();
        Livewire::test(ListAttendanceFaces::class)->callTableAction('delete', $f);
        $this->assertNull(AttendanceFace::find($f->id));
        Storage::disk('rieng')->assertMissing($f->photo);
    }

    public function test_cau_hinh_cham_cong_luu_duoc(): void
    {
        $this->actingAs($this->quanLy());
        Livewire::test(CauHinhChamCong::class)
            ->assertSet('data.cham_cong_ban_kinh', '150')
            ->set('data.cham_cong_lat', '10.7412345')
            ->set('data.cham_cong_ban_kinh', '200')
            ->set('data.cham_cong_vao', '08:30')
            ->call('luu')
            ->assertHasNoErrors();
        $this->assertSame('10.7412345', Setting::lay('cham_cong_lat'));
        $this->assertSame('200', Setting::lay('cham_cong_ban_kinh'));
        $this->assertSame('08:30', Setting::lay('cham_cong_vao'));
    }

    public function test_xoa_anh_cham_cong_sau_3_thang(): void
    {
        $u = $this->nhanVien();
        $this->dangKy($u);
        $this->cham($u, 'vao');
        $cu = Attendance::sole();
        $this->travelTo(Carbon::parse('2027-01-20 08:00'));
        $this->cham($u, 'vao');
        $moi = Attendance::whereDate('work_date', '2027-01-20')->sole();

        $this->artisan('cham-cong:don-anh')->assertSuccessful();
        Storage::disk('rieng')->assertMissing($cu->in_photo);
        $this->assertNull($cu->fresh()->in_photo);
        $this->assertNotNull($cu->fresh()->in_at); // giờ chấm vẫn giữ
        Storage::disk('rieng')->assertExists($moi->in_photo);
        Storage::disk('rieng')->assertExists(AttendanceFace::sole()->photo); // ảnh đăng ký không xoá
    }
}
