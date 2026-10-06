<?php

namespace Tests\Feature;

use App\Filament\Resources\AllianceDepartures\Pages\ListAllianceDepartures;
use App\Filament\Resources\AllianceSources\Pages\ListAllianceSources;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Modules\Alliance\Models\AllianceDeparture;
use Modules\Alliance\Models\AllianceSource;
use Modules\Alliance\Models\AllianceTour;
use Modules\Alliance\Services\DocBangLienMinh;
use Modules\Alliance\Services\DongBoLienMinh;
use Modules\Alliance\Services\TaiSheetLienMinh;
use Modules\Tour\Models\Tour;
use Modules\Tour\Models\TourDeparture;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;
use Tests\Support\MauSheetLienMinh;
use Tests\TestCase;

/**
 * Liên minh: đọc sheet đối tác (3 kiểu trình bày thật), đồng bộ, báo động,
 * cập nhật số chỗ tour PSV trên website, và phân quyền trang điều hành.
 */
class LienMinhTest extends TestCase
{
    use RefreshDatabase;

    private const LINK = 'https://docs.google.com/spreadsheets/d/1pHOzoNQktfB247uV3iESZ2_RLYMtElo8BBrRJE1vdIQ/edit?gid=1';

    protected function setUp(): void
    {
        parent::setUp();
        // Ngày "hôm nay" cố định = ngày cập nhật ghi trong sheet mẫu
        $this->travelTo(Carbon::parse('2026-10-06 09:00'));
    }

    private function doc(string $file): array
    {
        return collect(app(DocBangLienMinh::class)->doc($file)['dong'])
            ->keyBy(fn ($d) => mb_substr($d['ten_tour'], 0, 12).'|'.$d['ngay_di'])
            ->all();
    }

    // ------------------------------------------------------------------
    // Bộ đọc
    // ------------------------------------------------------------------

    public function test_doc_kieu_v1_khoi_gop_cho_va_nam(): void
    {
        $d = $this->doc(MauSheetLienMinh::v1());

        // Ngày đã qua (04, 09/2026) không lấy; tab ẩn không đọc
        $this->assertCount(9, $d);
        $this->assertArrayNotHasKey('SEOUL - NAMI|2026-04-08', $d);
        $this->assertFalse(collect($d)->contains(fn ($x) => str_contains($x['ten_tour'], 'NHÁP')));

        $seoul = $d['SEOUL - NAMI|2026-10-14'];
        $this->assertSame('SEOUL - NAMI - EVERLAND | 5N4D | TỐI THỨ 4', $seoul['ten_tour']);
        $this->assertSame(17990000, $seoul['gia'], '"Giá mới" thắng "Giá cũ", ghi theo nghìn');
        $this->assertSame(1000000, $seoul['hoa_hong']);
        $this->assertSame([29, 29, 'con_cho'], [$seoul['con_cho'], $seoul['tong_cho'], $seoul['trang_thai']]);
        $this->assertStringContainsString('VJ862', $seoul['hang_bay']);
        $this->assertSame('https://drive.google.com/drive/folders/chuong-trinh-seoul', $seoul['link_chuong_trinh']);

        $this->assertSame([0, 'het_cho'], [$d['SEOUL - NAMI|2026-10-28']['con_cho'], $d['SEOUL - NAMI|2026-10-28']['trang_thai']]);
        // Tab Tết lặp lại ngày 30/12 với số chỗ ít hơn → lấy số nhỏ
        $this->assertSame(2, $d['SEOUL - NAMI|2026-12-30']['con_cho']);
        // "T1/2027" + "*01/01" → năm 2027
        $this->assertArrayHasKey('SEOUL - NAMI|2027-01-01', $d);

        // NHẬN trống → SIZE − SURE − HOLD; giá "Chờ cập nhật" → không có giá
        $busan = $d['BUSAN - GYEO|2026-12-25'];
        $this->assertSame(20, $busan['con_cho']);
        $this->assertNull($busan['gia']);
        $this->assertArrayHasKey('BUSAN - GYEO|2027-02-05', $d, '"T2/27:" là tháng 2/2027');

        // Tour Nhật trong tab Tết: 30/12 thuộc 2026 dù tab ghi 2027
        $this->assertSame('6N5Đ', $d['TOKYO - FUJI|2026-12-29']['thoi_gian']);
        $this->assertSame(21, $d['TOKYO - FUJI|2027-02-03']['con_cho']);
    }

    public function test_doc_kieu_az_tin_chu_hien_thi_hon_ngay_luu_ben_duoi(): void
    {
        $d = $this->doc(MauSheetLienMinh::az());

        // Ô lưu năm 2025 nhưng nằm trong mục "THÁNG 10/2026"
        $this->assertSame(2, $d['ĐẾN HOKKAIDO|2026-10-19']['con_cho']);
        $this->assertSame('tam_ngung', $d['ĐẾN HOKKAIDO|2026-10-21']['trang_thai']);
        $this->assertSame('het_cho', $d['MÙA THU LÁ Đ|2026-11-23']['trang_thai']);
        $this->assertSame('https://docs.google.com/document/d/chuong-trinh-mua-thu', $d['MÙA THU LÁ Đ|2026-11-11']['link_chuong_trinh']);
        $this->assertSame('VNA', $d['MÙA THU LÁ Đ|2026-11-11']['hang_bay']);

        // Ô hiện "11/10" (lưu nhầm 10/11) → 11/10; tab không có cột chỗ → liên hệ
        $hn = $d['CĐV KIX//HND|2026-10-11'];
        $this->assertNull($hn['con_cho']);
        $this->assertSame('lien_he', $hn['trang_thai']);
        $this->assertSame(39900000, $hn['gia']);
    }

    public function test_doc_kieu_m_tour_nhieu_ngay_trong_mot_o(): void
    {
        $d = $this->doc(MauSheetLienMinh::mTour());

        $seoul = collect($d)->filter(fn ($x) => str_starts_with($x['ten_tour'], 'SEOUL - NAMI'));
        // 09/10, 20/10, 03/11 (ghi 2 lần → 1), 13/11
        $this->assertSame(['2026-10-09', '2026-10-20', '2026-11-03', '2026-11-13'], $seoul->pluck('ngay_di')->sort()->values()->all());
        $this->assertSame(17900000, $seoul->first()['gia']);
        $this->assertSame(16110000, $seoul->first()['gia_tre_em']);
        $this->assertSame('lien_he', $seoul->first()['trang_thai']);
        $this->assertSame('https://drive.google.com/file/d/lich-trinh-seoul', $seoul->first()['link_chuong_trinh']);

        // Không có dòng tên → tên mục + nhãn link (bỏ ngày) → 2 ngày gộp 1 tour
        $dong = collect($d)->filter(fn ($x) => str_contains($x['ten_tour'], 'MÙA ĐÔNG'));
        $this->assertCount(2, $dong);
        $this->assertCount(1, $dong->pluck('khoa_tour')->unique());

        // "Lễ 02/09" là nhãn, chỉ lấy 06/02/2027
        $tet = collect($d)->filter(fn ($x) => str_contains($x['ten_tour'], 'TẾT'));
        $this->assertSame(['2027-02-06'], $tet->pluck('ngay_di')->values()->all());
    }

    public function test_nhan_dien_link_google(): void
    {
        $this->assertSame('1q_Q1nTPiXerS8aoK5I8zl8dIPh9U7YdzYZ20SXoZCbg', TaiSheetLienMinh::maFile('https://docs.google.com/spreadsheets/d/1q_Q1nTPiXerS8aoK5I8zl8dIPh9U7YdzYZ20SXoZCbg/edit?gid=244595541#gid=244595541'));
        $this->assertSame('12wslN3eSZY8qcThmw0vqx_492vOCvTMJ', TaiSheetLienMinh::maFile('https://drive.google.com/file/d/12wslN3eSZY8qcThmw0vqx_492vOCvTMJ/view'));
        $this->assertNull(TaiSheetLienMinh::maFile('https://example.com/sheet.xlsx'));
    }

    // ------------------------------------------------------------------
    // Đồng bộ + báo động + website
    // ------------------------------------------------------------------

    private function nguon(): AllianceSource
    {
        return AllianceSource::create(['name' => 'V1 Travel', 'sheet_url' => self::LINK]);
    }

    private function dieuHanh(): User
    {
        $u = User::factory()->create();
        $u->assignRole(Role::findByName('dieu_hanh', 'web'));

        return $u;
    }

    /** Nội dung Google trả về ở lần tải tiếp theo (file xlsx, hoặc trang HTML). */
    private ?string $phanHoi = null;

    /**
     * Http::fake gọi nhiều lần thì luật cũ vẫn khớp trước → đăng ký MỘT lần,
     * đổi nội dung trả về qua $phanHoi.
     */
    private function traVe(string $file): void
    {
        $this->traVeNoiDung(file_get_contents($file));
    }

    private function traVeNoiDung(string $noiDung): void
    {
        $lanDau = $this->phanHoi === null;
        $this->phanHoi = $noiDung;
        if ($lanDau) {
            Http::fake(fn () => Http::response($this->phanHoi, 200));
        }
    }

    /** Sửa vài ô trong file mẫu V1 (tab đầu) để giả lập đối tác cập nhật sheet. */
    private function suaV1(array $o): string
    {
        $f = MauSheetLienMinh::v1();
        $sach = IOFactory::load($f);
        foreach ($o as $toaDo => $giaTri) {
            $sach->getSheet(0)->setCellValue($toaDo, $giaTri);
        }
        (new Xlsx($sach))->save($f);

        return $f;
    }

    public function test_dong_bo_luu_du_lieu_va_cap_nhat_so_cho_tour_psv(): void
    {
        $nguon = $this->nguon();
        $this->traVe(MauSheetLienMinh::v1());
        $kq = app(DongBoLienMinh::class)->dongBo($nguon);

        $this->assertTrue($kq['ok']);
        $this->assertSame(9, AllianceDeparture::count());
        $nguon->refresh();
        $this->assertNotNull($nguon->last_success_at);
        $this->assertSame('2026-10-06', $nguon->sheet_updated_on->toDateString());
        $this->assertSame(9, $nguon->departures_count);

        // Nối tour PSV với tour Seoul trong sheet
        $lm = AllianceTour::where('name', 'like', 'SEOUL - NAMI%')->firstOrFail();
        $tour = Tour::create(['slug' => 'seoul-'.uniqid(), 'name' => 'Seoul Nami 5N4Đ', 'type' => 'abroad', 'status' => 'published', 'adult_price' => 19990000, 'alliance_tour_id' => $lm->id]);
        $dot1410 = TourDeparture::create(['tour_id' => $tour->id, 'start_date' => '2026-10-14', 'seats_total' => 20, 'seats_left' => 20]);
        $dot2810 = TourDeparture::create(['tour_id' => $tour->id, 'start_date' => '2026-10-28', 'seats_total' => 20, 'seats_left' => 20]);
        $dotRieng = TourDeparture::create(['tour_id' => $tour->id, 'start_date' => '2026-10-20', 'seats_total' => 15, 'seats_left' => 15]);

        $this->assertSame(2, app(DongBoLienMinh::class)->capNhatTourPsv([$lm->id]));
        $this->assertSame([29, 29, 'open'], [$dot1410->fresh()->seats_left, $dot1410->fresh()->seats_total, $dot1410->fresh()->status]);
        $this->assertSame([0, 'full'], [$dot2810->fresh()->seats_left, $dot2810->fresh()->status], 'Sheet ghi FULL → web hết chỗ');
        $this->assertSame(15, $dotRieng->fresh()->seats_left, 'Ngày sheet không có thì giữ nguyên');

        // API công khai trả đúng số chỗ cho website
        $this->getJson("/api/v1/tours/{$tour->slug}")->assertOk()
            ->assertJsonFragment(['seats_left' => 29]);
    }

    public function test_bao_dong_khi_sheet_doi_va_khong_bao_lan_dau(): void
    {
        $nguoi = $this->dieuHanh();
        $nguon = $this->nguon();
        $this->traVe(MauSheetLienMinh::v1());
        app(DongBoLienMinh::class)->dongBo($nguon);
        $this->assertSame(0, $nguoi->notifications()->count(), 'Lần đọc đầu không báo hàng loạt');

        $lm = AllianceTour::where('name', 'like', 'SEOUL - NAMI%')->firstOrFail();
        $tour = Tour::create(['slug' => 'seoul-'.uniqid(), 'name' => 'Seoul', 'type' => 'abroad', 'status' => 'published', 'adult_price' => 1, 'alliance_tour_id' => $lm->id]);
        $dot = TourDeparture::create(['tour_id' => $tour->id, 'start_date' => '2026-10-28', 'seats_total' => 29, 'seats_left' => 0]);

        // Đối tác cập nhật: 14/10 còn 2 (sắp hết), 28/10 có lại 5 chỗ,
        // 04/11 bị xoá khỏi sheet
        $this->traVe($this->suaV1([
            'K9' => 2,
            'I10' => 24, 'K10' => 5, 'L10' => 'Mùa Thu',
            'E11' => '', 'K11' => '',
        ]));
        $kq = app(DongBoLienMinh::class)->dongBo($nguon->fresh());

        $this->assertSame(2, $kq['bao_dong']);
        $tb = $nguoi->notifications()->latest()->first();
        $this->assertNotNull($tb);
        $noiDung = $tb->data['body'];
        $this->assertStringContainsString('14/10: sắp hết, còn <strong>2 chỗ</strong>', $noiDung);
        $this->assertStringContainsString('28/10: <strong>có lại 5 chỗ</strong>', $noiDung);
        $this->assertStringContainsString('tour PSV đang bán', $noiDung);

        $this->assertFalse(AllianceDeparture::whereDate('departure_date', '2026-11-04')->exists(), 'Ngày bị xoá khỏi sheet thì xoá');
        $this->assertSame([5, 'open'], [$dot->fresh()->seats_left, $dot->fresh()->status], 'Website tự mở lại đợt');
    }

    public function test_sheet_bi_khoa_bao_loi_mot_lan_roi_bao_khi_doc_lai_duoc(): void
    {
        $nguoi = $this->dieuHanh();
        $nguon = $this->nguon();
        $this->traVe(MauSheetLienMinh::v1());
        app(DongBoLienMinh::class)->dongBo($nguon);

        $this->traVeNoiDung('<html><a href="https://accounts.google.com/ServiceLogin">Đăng nhập</a></html>');
        $kq = app(DongBoLienMinh::class)->dongBo($nguon->fresh());
        $this->assertFalse($kq['ok']);
        $this->assertStringContainsString('Bất kỳ ai có đường liên kết', $nguon->fresh()->last_error);
        $this->assertSame(9, AllianceDeparture::count(), 'Lỗi tải thì giữ dữ liệu cũ');

        app(DongBoLienMinh::class)->dongBo($nguon->fresh());
        $this->assertSame(1, $nguoi->notifications()->count(), 'Lỗi liên tiếp chỉ báo 1 lần');

        $this->traVe(MauSheetLienMinh::v1());
        app(DongBoLienMinh::class)->dongBo($nguon->fresh());
        $this->assertNull($nguon->fresh()->last_error);
        $this->assertSame(2, $nguoi->notifications()->count());
        $this->assertTrue($nguoi->notifications->contains(fn ($tb) => str_contains($tb->data['body'], 'đọc lại được')));
    }

    public function test_lay_ngay_di_tu_lien_minh_bo_qua_ngay_khong_ro_so_cho(): void
    {
        $nguon = AllianceSource::create(['name' => 'AZ', 'sheet_url' => self::LINK]);
        $this->traVe(MauSheetLienMinh::az());
        app(DongBoLienMinh::class)->dongBo($nguon);

        $hokkaido = AllianceTour::where('name', 'like', 'ĐẾN HOKKAIDO%')->firstOrFail();
        $tour = Tour::create(['slug' => 'hk-'.uniqid(), 'name' => 'Hokkaido', 'type' => 'abroad', 'status' => 'published', 'adult_price' => 1, 'alliance_tour_id' => $hokkaido->id]);
        $kq = app(DongBoLienMinh::class)->themNgayDi($tour);
        $this->assertSame(['them' => 2, 'bo_qua' => 0], $kq);
        $this->assertSame([2, 0], $tour->departures()->orderBy('start_date')->pluck('seats_left')->all(), 'Tạm ngưng nhận → 0 chỗ');

        $hn = AllianceTour::where('name', 'like', 'CĐV KIX%')->firstOrFail();
        $tour2 = Tour::create(['slug' => 'hn-'.uniqid(), 'name' => 'HN', 'type' => 'abroad', 'status' => 'published', 'adult_price' => 1, 'alliance_tour_id' => $hn->id]);
        $this->assertSame(['them' => 0, 'bo_qua' => 1], app(DongBoLienMinh::class)->themNgayDi($tour2));
    }

    public function test_lenh_chi_doc_sheet_toi_han(): void
    {
        $this->traVe(MauSheetLienMinh::v1());
        $moi = $this->nguon();
        $vuaDoc = AllianceSource::create(['name' => 'Vừa đọc', 'sheet_url' => self::LINK]);
        $vuaDoc->forceFill(['last_checked_at' => now()->subMinutes(3)])->save();
        $tat = AllianceSource::create(['name' => 'Tắt', 'sheet_url' => self::LINK, 'is_active' => false]);

        $this->artisan('lien-minh:dong-bo')->assertSuccessful();

        $this->assertNotNull($moi->fresh()->last_success_at);
        $this->assertNull($vuaDoc->fresh()->last_success_at);
        $this->assertNull($tat->fresh()->last_checked_at);
    }

    // ------------------------------------------------------------------
    // Trang quản trị
    // ------------------------------------------------------------------

    public function test_dieu_hanh_tra_cho_va_loc_theo_so_khach(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $nguon = $this->nguon();
        $this->traVe(MauSheetLienMinh::v1());
        app(DongBoLienMinh::class)->dongBo($nguon);
        $this->actingAs($this->dieuHanh());

        $con29 = AllianceDeparture::whereDate('departure_date', '2026-10-14')->first();
        $het = AllianceDeparture::whereDate('departure_date', '2026-10-28')->first();

        Livewire::test(ListAllianceDepartures::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$con29, $het])
            ->assertSee('Còn 29')
            ->filterTable('so_khach', ['khach' => 25])
            ->assertCanSeeTableRecords([$con29])
            ->assertCanNotSeeTableRecords([$het]);

        Livewire::test(ListAllianceDepartures::class)
            ->searchTable('tokyo')
            ->assertCountTableRecords(2);

        Livewire::test(ListAllianceSources::class)->assertOk()->assertSee('V1 Travel');
    }

    public function test_noi_tour_trong_form_cap_nhat_so_cho_ngay_va_nut_lay_ngay_di(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $nguon = $this->nguon();
        $this->traVe(MauSheetLienMinh::v1());
        app(DongBoLienMinh::class)->dongBo($nguon);
        $lm = AllianceTour::where('name', 'like', 'SEOUL - NAMI%')->firstOrFail();

        $nv = $this->dieuHanh();
        foreach (['View:Tour', 'ViewAny:Tour', 'Update:Tour', 'ViewAny:TourDeparture', 'Create:TourDeparture'] as $q) {
            $nv->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate($q, 'web'));
        }
        $this->actingAs($nv);

        $tour = Tour::create(['slug' => 'seoul-'.uniqid(), 'name' => 'Seoul', 'type' => 'abroad', 'status' => 'published', 'adult_price' => 1]);
        $dot = TourDeparture::create(['tour_id' => $tour->id, 'start_date' => '2026-11-04', 'seats_total' => 30, 'seats_left' => 30]);

        Livewire::test(\App\Filament\Resources\Tours\Pages\EditTour::class, ['record' => $tour->getRouteKey()])
            ->assertFormFieldExists('alliance_tour_id')
            ->fillForm(['alliance_tour_id' => $lm->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($lm->id, $tour->fresh()->alliance_tour_id);
        $this->assertSame(22, $dot->fresh()->seats_left, 'Nối xong là cập nhật ngay, không chờ 10 phút');

        Livewire::test(\App\Filament\Resources\Tours\RelationManagers\DeparturesRelationManager::class, [
            'ownerRecord' => $tour->fresh(),
            'pageClass' => \App\Filament\Resources\Tours\Pages\EditTour::class,
        ])
            ->assertSee('Theo sheet: còn 22')
            ->callTableAction('layTuLienMinh');

        // Seoul trong sheet có 5 ngày sắp tới, web đã có 04/11 → thêm 4
        $this->assertSame(5, $tour->departures()->count());
        $this->assertSame(0, $tour->departures()->whereDate('start_date', '2026-10-28')->value('seats_left'));
    }

    public function test_nhan_vien_khong_co_quyen_khong_vao_duoc(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $nv = User::factory()->create();
        $nv->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('ViewAny:Booking', 'web'));
        $this->actingAs($nv);

        $this->get('/admin/tra-cho-lien-minh')->assertForbidden();
        $this->get('/admin/alliance-sources')->assertForbidden();
    }
}
