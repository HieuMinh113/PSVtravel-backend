<?php

namespace Tests\Feature;

use App\Filament\Pages\BangCongThang;
use App\Filament\Pages\CauHinhChamCong;
use App\Filament\Pages\ChamCong;
use App\Filament\Resources\AttendanceFaces\Pages\ListAttendanceFaces;
use App\Filament\Resources\Attendances\BangChamCongCot;
use App\Filament\Resources\Attendances\Pages\ListAttendances;
use App\Models\Attendance;
use App\Models\AttendanceFace;
use App\Models\User;
use App\Services\ChamCong\ChamCong as DichVu;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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
        $this->assertSame('Đi trễ 8 giờ 30 phút (giờ vào 08:00)', $kq['vi_sao'][0]);
        $this->assertStringStartsWith('Không lấy được vị trí — chấm ở ngoài công ty phải được quản lý duyệt mới tính công', $kq['vi_sao'][1]);
    }

    public function test_cham_o_ngoai_phai_duyet_moi_tinh_cong_va_cham_vao_lai_tai_cong_ty(): void
    {
        $lan = $this->nhanVien('Lan');
        $vy = $this->nhanVien('Vy');
        $an = $this->nhanVien('An');
        foreach ([$lan, $vy, $an] as $u) {
            $this->dangKy($u);
        }
        $ql = $this->quanLy();
        $dv = app(DichVu::class);
        $xa = ['lat' => self::LAT + 0.02];
        $cua = fn (User $u) => Attendance::where('user_id', $u->id)->sole();

        // Lan 07:50 đúng giờ nhưng đứng ngoài 2,2 km → hỏi lý do, báo phải duyệt mới tính
        $kq = $this->cham($lan, 'vao', $xa);
        $this->assertStringStartsWith('Bạn đang cách công ty 2,2 km — chấm ở ngoài công ty phải được quản lý duyệt mới tính công', $kq['vi_sao'][0]);
        $kq = $this->cham($lan, 'vao', [...$xa, 'ly_do' => 'Đang gửi xe']);
        $this->assertStringContainsString('Chấm vào lại tại công ty', $kq['thong_bao']);
        $a = $cua($lan);
        $this->assertTrue($a->in_requires_approval);
        $this->assertSame('cho_duyet', $a->review_status);
        $this->assertSame(0.0, $a->cong()); // lượt vào ở ngoài chưa duyệt → chưa tính
        $anhCu = $a->in_photo;

        // Trang chấm công hiện nút chấm vào lại
        $this->actingAs($lan);
        $this->get('/admin/cham-cong')->assertSee('Chấm vào lại tại công ty');

        // 07:58 tới công ty chấm lại → lấy giờ mới, hết chờ duyệt, ảnh cũ bỏ
        $this->travelTo(Carbon::parse('2026-10-12 07:58'));
        $kq = $this->cham($lan, 'vao');
        $this->assertTrue($kq['ok']);
        $a = $cua($lan);
        $this->assertSame('07:58', $a->in_at->format('H:i'));
        $this->assertSame('trong', $a->in_location);
        $this->assertFalse($a->in_requires_approval);
        $this->assertNull($a->in_reason);
        $this->assertNull($a->review_status);
        $this->assertSame(1.0, $a->cong());
        Storage::disk('rieng')->assertMissing($anhCu);
        // Đã chấm ở công ty → không chấm vào lại được nữa
        $this->assertSame('Hôm nay bạn đã chấm vào lúc 07:58.', $this->cham($lan, 'vao')['loi']);

        // Vy chấm vào ở sân bay, ra ở công ty → cả ngày 0 công tới khi quản lý chấp nhận
        $this->travelTo(Carbon::parse('2026-10-12 07:55'));
        $this->cham($vy, 'vao', [...$xa, 'ly_do' => 'Đón đoàn ở sân bay']);
        $this->travelTo(Carbon::parse('2026-10-12 17:35'));
        $this->assertTrue($this->cham($vy, 'ra')['ok']);
        $a = $cua($vy);
        $this->assertSame('cho_duyet', $a->review_status); // chấm ra ở công ty không xoá chờ duyệt của lượt vào
        $this->assertSame(0.0, $a->cong());
        $this->assertStringContainsString('0 công (chấm ở ngoài, chờ duyệt)', (string) $this->moTaNgay($a));
        $this->assertStringContainsString('chưa chấp nhận thì cả ngày 0 công', view('filament.cham-cong.chi-tiet', ['a' => $a])->render());
        $dv->duyet($a, false, 'Không có lịch đón đoàn', $ql);
        $this->assertSame(0.0, $a->fresh()->cong());
        $this->assertStringContainsString('0 công (chấm ở ngoài, không chấp nhận)', (string) $this->moTaNgay($a->fresh()));
        $dv->duyet($a, true, null, $ql);
        $this->assertSame(1.0, $a->fresh()->cong());
        $this->assertFalse($a->fresh()->duocChamVaoLai()); // đã duyệt → khỏi chấm lại

        // An vào ở công ty, ra ở ngoài → 0 công; quay lại công ty chấm ra lại → đủ công
        $this->travelTo(Carbon::parse('2026-10-12 07:58'));
        $this->cham($an, 'vao');
        $this->travelTo(Carbon::parse('2026-10-12 17:40'));
        $kq = $this->cham($an, 'ra', $xa);
        $this->assertStringNotContainsString('bấm Huỷ', $kq['vi_sao'][0]); // chấm ra: không nhắc chấm lại
        $this->cham($an, 'ra', [...$xa, 'ly_do' => 'Ra cổng rồi mới nhớ']);
        $this->assertSame(0.0, $cua($an)->cong());
        $this->travelTo(Carbon::parse('2026-10-12 17:45'));
        $this->cham($an, 'ra');
        $this->assertNull($cua($an)->review_status);
        $this->assertSame(1.0, $cua($an)->cong());

        // Lượt chấm ở ngoài từ trước khi có quy tắc này (không gắn cờ) → giữ nguyên đủ công
        $cu = Attendance::create(['user_id' => $an->id, 'work_date' => '2026-10-09', 'in_at' => Carbon::parse('2026-10-09 07:50'), 'in_source' => 'cham',
            'in_location' => 'ngoai', 'out_at' => Carbon::parse('2026-10-09 17:40'), 'out_source' => 'cham', 'out_location' => 'trong']);
        $this->assertSame(1.0, $cu->fresh()->cong());
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

    public function test_luc_quet_chi_so_khuon_mat_khong_luu_khong_gui_anh(): void
    {
        $u = $this->nhanVien();
        $this->actingAs($u);
        $trang = Livewire::test(ChamCong::class)->instance();
        $this->assertSame(['khop' => false, 'loi' => 'Bạn chưa đăng ký khuôn mặt — tải lại trang để đăng ký.'], $trang->soKhuonMat($this->mat));

        $this->dangKy($u);
        $this->assertSame(['khop' => false], $trang->soKhuonMat($this->matKhac()));
        $this->assertSame(['khop' => false], $trang->soKhuonMat(['rác']));
        $this->assertSame(['khop' => true], $trang->soKhuonMat($this->mat));
        $this->assertSame(0, Attendance::count()); // chỉ so, không chấm

        // Lần so sai tính chung giới hạn với lần chấm: quá số lần thì tạm khoá cả hai
        for ($i = 2; $i < ChamCong::SO_LAN_KHONG_KHOP; $i++) {
            $trang->soKhuonMat($this->matKhac());
        }
        $kq = $trang->soKhuonMat($this->mat);
        $this->assertFalse($kq['khop']);
        $this->assertTrue($kq['qua_nhieu']);
        $this->assertTrue($trang->chamCong('vao', ['lat' => self::LAT, 'lng' => self::LNG, 'anh' => $this->anh(), 'descriptor' => $this->mat, 'chi_khi_khop' => true])['qua_nhieu']);
    }

    public function test_trang_cham_cong_nap_bo_nhan_dien_va_wasm(): void
    {
        $this->actingAs($this->nhanVien());
        $d = Livewire::test(ChamCong::class)->assertOk()->instance()->duLieuTrang();
        $this->assertStringEndsWith('/vendor/face-api/face-api.js', $d['thuVien']);
        $this->assertStringEndsWith('/vendor/face-api/wasm/', $d['wasm']); // máy không có card đồ hoạ chạy WebAssembly
        $this->assertFileExists(public_path('vendor/face-api/wasm/tfjs-backend-wasm.wasm'));
        $this->assertFileExists(public_path('vendor/face-api/wasm/tfjs-backend-wasm-simd.wasm'));
    }

    /** Cùng người, lệch mỗi số một chút (camera khác) — 0,04 → độ lệch ≈ 0,45. */
    private function matLech(float $d): array
    {
        return array_map(fn ($v) => round($v + $d, 6), $this->mat);
    }

    public function test_may_tinh_khop_lan_dau_luu_them_mau_lan_sau_so_voi_mau_gan_nhat(): void
    {
        $u = $this->nhanVien();
        app(DichVu::class)->dangKy($u, $this->mat, $this->anh(), 'dien_thoai');
        $dv = app(DichVu::class);
        $f = AttendanceFace::where('user_id', $u->id)->sole();
        $this->assertSame('dien_thoai', $f->device);
        $this->assertFalse($dv->khopKhuonMat($u, $this->matLech(0.055))); // webcam lệch 0,62: chưa khớp

        // Webcam khớp lần đầu (0,45 < 0,55) → lưu thêm mẫu máy tính
        $kq = $this->cham($u, 'vao', ['descriptor' => $this->matLech(0.04), 'thiet_bi' => 'may_tinh', 'chi_khi_khop' => true]);
        $this->assertTrue($kq['ok'], json_encode($kq));
        $f->refresh();
        $this->assertSame(['may_tinh'], array_keys($f->extra_descriptors));
        $this->assertSame($this->matLech(0.04), $f->extra_descriptors['may_tinh']['descriptor']);
        $this->assertArrayNotHasKey('extra_descriptors', $f->toArray()); // không lộ ra ngoài
        $a = Attendance::sole();
        $this->assertSame('may_tinh', $a->in_device);
        $this->assertEqualsWithDelta(0.453, $a->in_face_distance, 0.002);

        // Lần sau: so với mẫu gần nhất → webcam lệch 0,62 so với ảnh đăng ký vẫn khớp
        $this->assertTrue($dv->khopKhuonMat($u, $this->matLech(0.055)));
        $this->assertFalse($dv->khopKhuonMat($u, $this->matLech(-0.08)));
        $this->travelTo(Carbon::parse('2026-10-12 17:35'));
        $this->assertTrue($this->cham($u, 'ra', ['descriptor' => $this->matLech(0.055), 'thiet_bi' => 'may_tinh', 'chi_khi_khop' => true])['ok']);
        $this->assertTrue(Attendance::sole()->out_face_ok);
        $this->assertSame($this->matLech(0.04), $f->refresh()->extra_descriptors['may_tinh']['descriptor']); // mỗi loại máy một mẫu

        // Không nối mẫu: khớp nhờ mẫu thêm (lệch 0,62 so với ảnh đăng ký) thì KHÔNG lưu mẫu cho loại máy khác
        $f->forceFill(['device' => null])->save();
        $this->assertTrue($this->cham($u, 'ra', ['descriptor' => $this->matLech(0.055), 'thiet_bi' => 'dien_thoai'])['ok']);
        $this->assertSame(['may_tinh'], array_keys($f->refresh()->extra_descriptors));

        // Không khớp (gửi quản lý duyệt) thì không lưu mẫu
        $ha = $this->nhanVien('Hà');
        $this->dangKy($ha);
        $this->assertTrue($this->cham($ha, 'vao', ['descriptor' => $this->matKhac(), 'thiet_bi' => 'may_tinh', 'ly_do' => 'Kẹt xe'])['ok']);
        $this->assertNull(AttendanceFace::where('user_id', $ha->id)->sole()->extra_descriptors);

        $chiTiet = view('filament.cham-cong.chi-tiet', ['a' => Attendance::where('user_id', $u->id)->sole()])->render();
        $this->assertStringContainsString('Đã lưu thêm mẫu mặt cho máy tính', $chiTiet);
    }

    public function test_ghi_vi_sao_khong_co_vi_tri_loai_may_va_thoi_gian_quet(): void
    {
        $u = $this->nhanVien();
        $this->dangKy($u);
        $khongViTri = ['lat' => null, 'lng' => null, 'accuracy' => null, 'vi_tri_loi' => 'chua_cho_phep', 'thiet_bi' => 'dien_thoai', 'thoi_gian_quet' => 3240];

        // Không có vị trí → hỏi lý do, nói rõ vì sao
        $kq = $this->cham($u, 'vao', $khongViTri);
        $this->assertTrue($kq['can_ly_do']);
        $this->assertStringStartsWith('Không lấy được vị trí — trình duyệt chưa được cho phép lấy vị trí — chấm ở ngoài công ty phải được quản lý duyệt', $kq['vi_sao'][0]);
        $this->assertTrue($this->cham($u, 'vao', [...$khongViTri, 'ly_do' => 'Điện thoại chặn vị trí'])['ok']);
        $a = Attendance::sole();
        $this->assertSame('khong_ro', $a->in_location);
        $this->assertSame('chua_cho_phep', $a->in_location_error);
        $this->assertTrue($a->in_requires_approval); // không cho phép vị trí → phải duyệt mới tính
        $this->assertSame('cho_duyet', $a->review_status);
        $this->assertSame('dien_thoai', $a->in_device);
        $this->assertSame(3240, $a->in_scan_ms);

        $chiTiet = view('filament.cham-cong.chi-tiet', ['a' => $a])->render();
        $this->assertStringContainsString('Vì sao: trình duyệt chưa được cho phép lấy vị trí', $chiTiet);
        $this->assertStringContainsString('Điện thoại · quét 3,2 giây', $chiTiet);
        $this->assertSame('Không rõ vị trí (chưa cho phép)', BangChamCongCot::moTa($a, 'in'));

        // Mã lạ / không có toạ độ mà tự nhận "sai số lớn" → để trống, không đoán
        $this->travelTo(Carbon::parse('2026-10-12 17:35'));
        foreach (['khong_lay_duoc' => 'khong_lay_duoc', 'xyz' => null, 'sai_so_lon' => null] as $ma => $ghi) {
            $this->cham($u, 'ra', ['lat' => null, 'lng' => null, 'vi_tri_loi' => $ma, 'ly_do' => 'Không rõ']);
            $this->assertSame($ghi, Attendance::sole()->out_location_error);
        }

        // Máy tính đoán vị trí qua mạng: xa 5 km, sai số 3 km → không rõ, vì sai số lớn
        $kq = $this->cham($u, 'ra', ['lat' => self::LAT + 0.05, 'accuracy' => 3000, 'vi_tri_loi' => 'het_gio']);
        $this->assertSame(['Vị trí không chính xác (sai số 3,0 km)'], $kq['vi_sao']);
        $this->cham($u, 'ra', ['lat' => self::LAT + 0.05, 'accuracy' => 3000, 'ly_do' => 'Wifi công ty']);
        $this->assertSame('sai_so_lon', Attendance::sole()->out_location_error);

        // Ở công ty → không có lý do; thời gian quét rác thì bỏ
        $this->cham($u, 'ra', ['thoi_gian_quet' => 'abc']);
        $a = Attendance::sole();
        $this->assertSame('trong', $a->out_location);
        $this->assertNull($a->out_location_error);
        $this->assertNull($a->out_scan_ms);

        // Trình duyệt không báo loại máy → đoán theo User-Agent
        $this->actingAs($u);
        $trang = Livewire::test(ChamCong::class)->instance();
        $trang->chamCong('ra', ['lat' => self::LAT, 'lng' => self::LNG, 'anh' => $this->anh(), 'descriptor' => $this->mat, 'thiet_bi' => 'tivi']);
        $this->assertSame('may_tinh', Attendance::sole()->out_device);
        request()->headers->set('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Mobile/15E148');
        $this->assertSame('dien_thoai', ChamCong::thietBi(null));
        $this->assertSame('may_tinh', ChamCong::thietBi('may_tinh'));
    }

    public function test_trang_cham_cong_hoi_vi_tri_truoc_khi_mo_camera(): void
    {
        $u = $this->nhanVien();
        $this->dangKy($u);
        $this->actingAs($u);
        Livewire::test(ChamCong::class)
            ->assertSee('Bật vị trí trước khi chấm công')
            ->assertSee('Trình duyệt đang chặn vị trí')
            ->assertSee('Bước 1/2 — Cho phép vị trí')
            ->assertSee('canViTri: true', false);

        // Chưa đặt toạ độ công ty → không xét vị trí, không hỏi trước
        Setting::where('key', 'cham_cong_lat')->delete();
        Cache::flush();
        Livewire::test(ChamCong::class)
            ->assertDontSee('Bật vị trí trước khi chấm công')
            ->assertSee('canViTri: false', false);

        // Đăng ký lần đầu ghi loại máy
        $ha = $this->nhanVien('Hà');
        $this->actingAs($ha);
        $this->assertTrue(Livewire::test(ChamCong::class)->instance()->dangKyKhuonMat($this->mat, $this->anh(), true, 'may_tinh')['ok']);
        $this->assertSame('may_tinh', AttendanceFace::where('user_id', $ha->id)->sole()->device);
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
        $this->assertSame(1.5, $so['cong']); // 13/10 quên chấm ra → nửa công
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

        Livewire::test(CauHinhChamCong::class)
            ->assertSet('data.cham_cong_phut_ly_do', null)
            ->set('data.cham_cong_phut_tre', '0')
            ->set('data.cham_cong_phut_ly_do', '5')
            ->call('luu')
            ->assertHasNoErrors();
        $this->assertSame(0, \App\Services\ChamCong\CauHinhChamCong::phutChoPhep());
        $this->assertSame(5, \App\Services\ChamCong\CauHinhChamCong::phutCanLyDo());
    }

    private function cauHinh(array $gt): void
    {
        foreach ($gt as $k => $v) {
            Setting::updateOrCreate(['key' => "cham_cong_{$k}"], ['value' => $v, 'group' => 'cham_cong', 'label' => $k]);
        }
    }

    public function test_tinh_tre_theo_phut_bo_qua_giay(): void
    {
        $this->cauHinh(['vao' => '08:30', 'phut_tre' => '0']);
        $u = $this->nhanVien();
        $this->dangKy($u);

        // 08:30:45 vẫn là 08:30 → đúng giờ (trước đây: "Đi trễ 0 phút", bắt ghi lý do)
        $this->travelTo(Carbon::parse('2026-10-12 08:30:45'));
        $kq = $this->cham($u, 'vao');
        $this->assertTrue($kq['ok']);
        $this->assertSame(0, Attendance::sole()->late_minutes);
        Attendance::query()->delete();

        // 08:31:10 → trễ 1 phút, chưa đặt ô "phải ghi lý do" → hỏi lý do như cũ
        $this->travelTo(Carbon::parse('2026-10-12 08:31:10'));
        $this->assertSame(['Đi trễ 1 phút (giờ vào 08:30)'], $this->cham($u, 'vao')['vi_sao']);

        // Không tính trễ trong 1 phút: 08:31:59 vẫn đúng giờ, 08:32 trễ 2 phút (tính từ 08:30)
        $this->cauHinh(['phut_tre' => '1']);
        $this->travelTo(Carbon::parse('2026-10-12 08:31:59'));
        $this->assertTrue($this->cham($u, 'vao')['ok']);
        $this->assertSame(0, Attendance::sole()->late_minutes);
        Attendance::query()->delete();
        $this->travelTo(Carbon::parse('2026-10-12 08:32:05'));
        $this->assertSame(['Đi trễ 2 phút (giờ vào 08:30)'], $this->cham($u, 'vao')['vi_sao']);
    }

    public function test_tre_it_van_tinh_phut_nhung_khong_hoi_ly_do(): void
    {
        $this->cauHinh(['vao' => '08:30', 'phut_tre' => '0', 'phut_ly_do' => '5']);
        $lan = $this->nhanVien('Lan');
        $vy = $this->nhanVien('Vy');
        $an = $this->nhanVien('An');
        foreach ([$lan, $vy, $an] as $u) {
            $this->dangKy($u);
        }

        // 08:31 → trễ 1 phút, không phải ghi lý do, không chờ duyệt
        $this->travelTo(Carbon::parse('2026-10-12 08:31:30'));
        $kq = $this->cham($lan, 'vao');
        $this->assertTrue($kq['ok']);
        $this->assertStringContainsString('trễ 1 phút', $kq['thong_bao']);
        $a = Attendance::where('user_id', $lan->id)->sole();
        $this->assertSame(1, $a->late_minutes);
        $this->assertNull($a->review_status);

        // Đúng 5 phút vẫn chưa phải ghi lý do; 6 phút thì phải ghi và chờ duyệt
        $this->travelTo(Carbon::parse('2026-10-12 08:35:20'));
        $this->assertTrue($this->cham($vy, 'vao')['ok']);
        $this->assertSame(5, Attendance::where('user_id', $vy->id)->sole()->late_minutes);
        $this->travelTo(Carbon::parse('2026-10-12 08:36:00'));
        $this->assertSame(['Đi trễ 6 phút (giờ vào 08:30)'], $this->cham($an, 'vao')['vi_sao']);
        $this->assertTrue($this->cham($an, 'vao', ['ly_do' => 'Kẹt xe'])['ok']);
        $a = Attendance::where('user_id', $an->id)->sole();
        $this->assertSame(6, $a->late_minutes);
        $this->assertSame('cho_duyet', $a->review_status);

        // Bảng công tháng đếm cả trễ ít
        $this->assertSame(12, BangCongThang::cong(Attendance::all())['tre_phut']);
    }

    public function test_phut_tre_chot_luc_cham_doi_cau_hinh_khong_doi_dong_cu(): void
    {
        $this->cauHinh(['vao' => '08:30', 'phut_tre' => '5']);
        $u = $this->nhanVien();
        $this->dangKy($u);
        $this->travelTo(Carbon::parse('2026-10-12 08:33'));
        $this->cham($u, 'vao');

        // Quản lý đổi "không tính trễ" còn 0 phút, nhân viên chấm ra / được duyệt sau đó
        $this->cauHinh(['phut_tre' => '0']);
        $this->travelTo(Carbon::parse('2026-10-12 17:35'));
        $this->cham($u, 'ra');
        $a = Attendance::sole();
        $this->assertSame(0, $a->late_minutes);
        app(DichVu::class)->duyet($a, true, null, $this->quanLy());
        $this->assertSame(0, $a->fresh()->late_minutes);

        // Sửa giờ vào của dòng → tính lại theo cấu hình hiện tại
        $a->forceFill(['in_at' => Carbon::parse('2026-10-12 08:34')])->save();
        $this->assertSame(4, $a->fresh()->late_minutes);
    }

    public function test_sai_so_gps_lon_hon_ban_kinh_la_khong_ro_vi_tri(): void
    {
        $this->cauHinh(['ban_kinh' => '50']);
        $u = $this->nhanVien();
        $this->dangKy($u);

        // Điểm GPS cách 33 m (trong 50 m) nhưng sai số ±80 m → không dám nói là ở công ty
        $kq = $this->cham($u, 'vao', ['accuracy' => 80]);
        $this->assertSame(['Vị trí không chính xác (sai số 80 m)'], $kq['vi_sao']);
        $this->assertTrue($this->cham($u, 'vao', ['accuracy' => 80, 'ly_do' => 'Ngồi trong phòng họp'])['ok']);
        $a = Attendance::sole();
        $this->assertSame('khong_ro', $a->in_location);
        $this->assertSame('sai_so_lon', $a->in_location_error);
        $this->assertNull($a->review_status); // đúng giờ → chỉ ghi lý do, không chờ duyệt

        // Sai số nhỏ hơn bán kính → trong công ty như cũ
        $this->travelTo(Carbon::parse('2026-10-12 17:35'));
        $this->assertTrue($this->cham($u, 'ra', ['accuracy' => 20])['ok']);
        $this->assertSame('trong', $a->fresh()->out_location);
    }

    public function test_ve_som_hoac_quen_cham_ra_tinh_nua_cong(): void
    {
        $u = $this->nhanVien();
        $this->dangKy($u);
        $ql = $this->quanLy();
        $dv = app(DichVu::class);
        $ngay = fn (string $d) => Attendance::whereDate('work_date', $d)->sole();

        // T2 12/10: về 16:00 → nửa công, báo trước cho nhân viên; quản lý chấp nhận → đủ công
        $this->travelTo(Carbon::parse('2026-10-12 07:55'));
        $this->cham($u, 'vao');
        $this->travelTo(Carbon::parse('2026-10-12 16:00'));
        $this->assertStringContainsString('tính 0,5 công', $this->cham($u, 'ra')['vi_sao'][0]);
        $this->cham($u, 'ra', ['ly_do' => 'Đi khám bệnh']);
        $a = $ngay('2026-10-12');
        $this->assertSame(0.5, $a->cong());
        $this->assertSame('ve_som', $a->nuaCong());
        $this->assertStringContainsString('0,5 công (về sớm)', (string) $this->moTaNgay($a));
        $dv->duyet($a, false, 'Không đủ giấy tờ', $ql);
        $this->assertSame(0.5, $a->fresh()->cong());
        $dv->duyet($a, true, null, $ql);
        $this->assertSame(1.0, $a->fresh()->cong());

        // T3 13/10: về 17:26 (trong 5 phút không tính) → đủ công
        $this->travelTo(Carbon::parse('2026-10-13 07:58'));
        $this->cham($u, 'vao');
        $this->travelTo(Carbon::parse('2026-10-13 17:26'));
        $this->assertTrue($this->cham($u, 'ra')['ok']);
        $this->assertSame(1.0, $ngay('2026-10-13')->cong());

        // T4 14/10: chưa chấm ra — trong ngày vẫn tạm tính đủ, qua ngày thành nửa công
        $this->travelTo(Carbon::parse('2026-10-14 07:58'));
        $this->cham($u, 'vao');
        $this->travelTo(Carbon::parse('2026-10-14 15:00'));
        $this->assertSame(1.0, $ngay('2026-10-14')->cong());
        $this->travelTo(Carbon::parse('2026-10-15 09:00'));
        $this->assertSame(0.5, $ngay('2026-10-14')->cong());
        $this->assertSame('thieu_ra', $ngay('2026-10-14')->nuaCong());
        // Xin bổ sung giờ ra: chờ duyệt vẫn nửa công, duyệt rồi đủ công
        $dv->boSung($u, Carbon::parse('2026-10-14'), 'ra', '17:40', 'Quên chấm ra');
        $this->assertSame(0.5, $ngay('2026-10-14')->cong());
        $dv->duyet($ngay('2026-10-14'), true, null, $ql);
        $this->assertSame(1.0, $ngay('2026-10-14')->cong());

        // T7 17/10: về 11:00 (giờ ra 12:00) → 0,25 công
        $this->travelTo(Carbon::parse('2026-10-17 07:55'));
        $this->cham($u, 'vao');
        $this->travelTo(Carbon::parse('2026-10-17 11:00'));
        $this->assertStringContainsString('tính 0,25 công', $this->cham($u, 'ra')['vi_sao'][0]);
        $this->cham($u, 'ra', ['ly_do' => 'Việc nhà']);
        $this->assertSame(0.25, $ngay('2026-10-17')->cong());

        // T2 19/10: lỡ bấm ra sớm, cuối giờ chấm ra lại → đủ công
        $this->travelTo(Carbon::parse('2026-10-19 07:55'));
        $this->cham($u, 'vao');
        $this->travelTo(Carbon::parse('2026-10-19 15:00'));
        $this->cham($u, 'ra', ['ly_do' => 'Bấm nhầm']);
        $this->assertSame(0.5, $ngay('2026-10-19')->cong());
        $this->travelTo(Carbon::parse('2026-10-19 17:35'));
        $this->assertTrue($this->cham($u, 'ra')['ok']);
        $this->assertSame(1.0, $ngay('2026-10-19')->cong());
    }

    public function test_quen_cham_vao_tinh_nua_cong(): void
    {
        $u = $this->nhanVien();
        $this->dangKy($u);
        $ql = $this->quanLy();
        $dv = app(DichVu::class);
        $ngay = fn (string $d) => Attendance::whereDate('work_date', $d)->sole();

        // T2 12/10: không chấm vào, chỉ chấm ra → báo ngay lúc chấm ra, nửa công
        $this->travelTo(Carbon::parse('2026-10-12 17:35'));
        $kq = $this->cham($u, 'ra');
        $this->assertTrue($kq['ok']);
        $this->assertStringContainsString('chưa chấm vào', $kq['canh_bao'][0]);
        $this->assertStringContainsString('0,5 công', $kq['canh_bao'][0]);
        $a = $ngay('2026-10-12');
        $this->assertSame(0.5, $a->cong());
        $this->assertSame('thieu_vao', $a->nuaCong());
        $this->assertStringContainsString('0,5 công (thiếu giờ vào)', (string) $this->moTaNgay($a));

        // Quản lý chấp nhận lý do khác trong ngày không bù được giờ vào
        $dv->duyet($a, true, null, $ql);
        $this->assertSame(0.5, $a->fresh()->cong());

        // Xin bổ sung giờ vào: chờ duyệt vẫn nửa công, duyệt rồi đủ công; trễ tính theo giờ khai
        $dv->boSung($u, Carbon::parse('2026-10-12'), 'vao', '08:20', 'Quên chấm vào');
        $this->assertSame(0.5, $ngay('2026-10-12')->cong());
        $dv->duyet($ngay('2026-10-12'), true, null, $ql);
        $this->assertSame(1.0, $ngay('2026-10-12')->cong());
        $this->assertSame(20, $ngay('2026-10-12')->late_minutes);

        // Đã xin bổ sung giờ vào (đang chờ duyệt) rồi mới chấm ra → không nhắc lại
        $this->travelTo(Carbon::parse('2026-10-13 10:00'));
        $dv->boSung($u, Carbon::parse('2026-10-13'), 'vao', '07:58', 'Điện thoại hết pin');
        $this->travelTo(Carbon::parse('2026-10-13 17:35'));
        $this->assertSame([], $this->cham($u, 'ra')['canh_bao']);

        // T7 17/10: chỉ chấm ra → 0,25 công
        $this->travelTo(Carbon::parse('2026-10-17 12:05'));
        $this->assertStringContainsString('0,25 công', $this->cham($u, 'ra')['canh_bao'][0]);
        $this->assertSame(0.25, $ngay('2026-10-17')->cong());

        // Bảng công tháng đếm số ngày thiếu chấm vào; ngày công hiện đủ 2 số lẻ
        $so = BangCongThang::cong(Attendance::all());
        $this->assertSame(1, $so['thieu_vao']);
        $this->assertSame('1,75', BangCongThang::so($so['cong'])); // 1 + 0,5 + 0,25
        $this->assertSame('3,5', BangCongThang::so(3.5));
        $this->assertSame('3', BangCongThang::so(3.0));
    }

    public function test_ap_lai_quy_tac_cham_ngoai_cho_luot_cham_tu_01_11(): void
    {
        $u = $this->nhanVien();
        $dong = fn (string $ngay, array $them) => Attendance::create(['user_id' => $u->id, 'work_date' => $ngay,
            'in_at' => Carbon::parse("$ngay 07:55"), 'in_source' => 'cham', 'in_location' => 'trong', 'in_lat' => self::LAT,
            'out_at' => Carbon::parse("$ngay 17:35"), 'out_source' => 'cham', 'out_location' => 'trong', 'out_lat' => self::LAT, ...$them]);
        $ngoaiChuaXet = $dong('2026-11-03', ['in_location' => 'ngoai', 'in_reason' => 'Gửi xe']);
        $daChapNhan = $dong('2026-11-04', ['in_location' => 'ngoai', 'review_status' => 'chap_nhan']);
        $khongViTri = $dong('2026-11-05', ['out_location' => 'khong_ro', 'out_lat' => null]);
        $saiSoLon = $dong('2026-11-06', ['in_location' => 'khong_ro', 'in_location_error' => 'sai_so_lon']);
        $boSung = $dong('2026-11-09', ['in_source' => 'bo_sung', 'in_location' => null, 'review_status' => 'chap_nhan']);
        $thang10 = $dong('2026-10-30', ['in_location' => 'ngoai']);
        foreach ([$ngoaiChuaXet, $daChapNhan, $khongViTri, $saiSoLon, $boSung, $thang10] as $a) {
            $this->assertSame(1.0, $a->fresh()->cong());
        }

        (require database_path('migrations/2026_11_11_100000_duyet_lai_cham_ngoai_tu_thang_11.php'))->up();

        // Chấm ngoài từ 01/11 chưa ai xét → chờ duyệt, 0 công; không có toạ độ cũng vậy
        $this->assertTrue($ngoaiChuaXet->fresh()->in_requires_approval);
        $this->assertSame('cho_duyet', $ngoaiChuaXet->fresh()->review_status);
        $this->assertSame(0.0, $ngoaiChuaXet->fresh()->cong());
        $this->assertTrue($khongViTri->fresh()->out_requires_approval);
        $this->assertSame(0.0, $khongViTri->fresh()->cong());
        // Đã chấp nhận → giữ; sai số lớn, bổ sung, tháng 10 → không đụng
        $this->assertSame(1.0, $daChapNhan->fresh()->cong());
        $this->assertFalse($saiSoLon->fresh()->in_requires_approval);
        $this->assertNull($saiSoLon->fresh()->review_status);
        $this->assertSame(1.0, $boSung->fresh()->cong());
        $this->assertFalse($thang10->fresh()->in_requires_approval);
        $this->assertSame(1.0, $thang10->fresh()->cong());

        // Quản lý chấp nhận → đủ công
        app(DichVu::class)->duyet($ngoaiChuaXet->fresh(), true, null, $this->quanLy());
        $this->assertSame(1.0, $ngoaiChuaXet->fresh()->cong());
    }

    /** Dòng mô tả dưới cột Ngày của bảng chấm công: "T2 · 0,5 công (về sớm)". */
    private function moTaNgay(Attendance $a): ?string
    {
        $cot = BangChamCongCot::cot(false)[0];

        return $cot->record($a)->getDescriptionBelow();
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
