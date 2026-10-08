<?php

namespace Tests\Feature;

use App\Filament\Resources\AllianceDepartures\Pages\ListAllianceDepartures;
use App\Filament\Resources\AllianceSources\Pages\EditAllianceSource;
use App\Filament\Resources\AllianceSources\Pages\ListAllianceSources;
use App\Filament\Resources\Tours\Pages\EditTour;
use App\Filament\Resources\Tours\RelationManagers\DeparturesRelationManager;
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
use Modules\Alliance\Services\SoanTinLienMinh;
use Modules\Alliance\Services\TaiSheetLienMinh;
use Modules\Tour\Models\Tour;
use Modules\Tour\Models\TourDeparture;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
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

    public function test_doc_kieu_vgi_thang_kem_danh_sach_ngay(): void
    {
        $d = $this->doc(MauSheetLienMinh::vgi());

        // "Tháng 10: 15, 22, 29" + "Tháng 11: 5, 12⏎Tháng 12: 03, 10" + "Tháng 12: 31 (TẾT DƯƠNG)"
        foreach (['2026-10-15', '2026-10-22', '2026-10-29', '2026-11-05', '2026-11-12', '2026-12-03', '2026-12-10', '2026-12-31'] as $ngay) {
            $this->assertArrayHasKey('HÀ NỘI - TRƯ|'.$ngay, $d, $ngay);
        }
        $this->assertSame(10490000, $d['HÀ NỘI - TRƯ|2026-10-15']['gia']);
        $this->assertSame(800000, $d['HÀ NỘI - TRƯ|2026-10-15']['hoa_hong'], 'COM "800k"');
        $this->assertSame('https://docs.google.com/document/d/phct-6n5d', $d['HÀ NỘI - TRƯ|2026-10-15']['link_chuong_trinh']);
        // Biến thể liệt kê lại từ tháng 10 → vẫn năm nay, không đẩy sang 2027
        $this->assertSame(1000000, $d['HÀ NỘI - TRƯ|2026-10-17']['hoa_hong'], 'COM "1000K"');
        $this->assertArrayNotHasKey('HÀ NỘI - TRƯ|2027-10-17', $d);
        // Tab giữ cả lịch 2026 đã qua (có mốc "Tức 1 Tết" = Tết 2026) → không sinh ngày giả
        $this->assertFalse(collect($d)->contains(fn ($x) => $x['tab'] === 'ĐÔNG NAM Á'));
    }

    public function test_doc_kieu_vvt_cot_thang_cot_ngay_va_hang_tuan(): void
    {
        $tatCa = app(DocBangLienMinh::class)->doc(MauSheetLienMinh::vvt())['dong'];
        $ngay = collect($tatCa)->whereNotNull('ngay_di')->pluck('ngay_di')->sort()->values()->all();

        // "Tháng 1|21" (tựa NĂM 2026 → đã qua), "Lễ 2/9|28" = 28/08 (đã qua) → không có
        $this->assertSame(['2026-12-10', '2026-12-24', '2027-01-08', '2027-01-15', '2027-01-22', '2027-02-02', '2027-02-06'], $ngay);
        $this->assertSame('HÀN QUỐC – SEOUL MONO', collect($tatCa)->firstWhere('ngay_di', '2026-12-10')['ten_tour']);
        $this->assertSame('SINGAPORE - MALAYSIA 5N4Đ', collect($tatCa)->firstWhere('ngay_di', '2027-02-06')['ten_tour'], '"06 - 10/02/2027" → 06/02');

        $tuan = collect($tatCa)->whereNull('ngay_di');
        $this->assertSame(['Thứ 5 hằng tuần', 'Thứ 5 hằng tuần', 'Thứ 3, 6, Chủ nhật hằng tuần'], $tuan->pluck('lich_tuan')->values()->all());
        // Cùng tên khác số ngày (3N2Đ / 4N3Đ) là 2 tour
        $this->assertSame([3450000, 3750000], $tuan->take(2)->pluck('gia')->values()->all());
    }

    public function test_doc_kieu_trieu_hao_cot_ngay_khong_tieu_de_com_ag_dong(): void
    {
        $d = collect(app(DocBangLienMinh::class)->doc(MauSheetLienMinh::trieuHao())['dong'])->keyBy('ngay_di');

        $this->assertSame(['2026-10-24', '2026-10-31', '2026-11-03', '2026-11-10'], $d->keys()->sort()->values()->all());
        $this->assertSame([17990000, 1000000, 25, 29, 'con_cho'], [$d['2026-10-24']['gia'], $d['2026-10-24']['hoa_hong'], $d['2026-10-24']['con_cho'], $d['2026-10-24']['tong_cho'], $d['2026-10-24']['trang_thai']], 'Lấy COM AG, không lấy TỔNG COM / LN');
        $this->assertSame([16990000, 'het_cho'], [$d['2026-10-31']['gia'], $d['2026-10-31']['trang_thai']], '"16.990K", NHẬN "ĐÓNG"');
        $this->assertSame('het_cho', $d['2026-11-10']['trang_thai'], 'Cột không tiêu đề ghi "ĐÓNG ĐOÀN"');
    }

    public function test_doc_kieu_hanvina_ngay_to_do_va_cot_khai_tay(): void
    {
        $tuyChon = ['mau_do' => 'theo_chu_thich', 'cot_tay' => ['BẮC KINH - THƯỢNG HẢI HCM' => ['ten' => 'B', 'thoi_gian' => 'D', 'hang_bay' => 'E', 'ngay_di' => 'F', 'gia' => 'H', 'hoa_hong' => 'I', 'ghi_chu' => 'J']]];
        $kq = app(DocBangLienMinh::class)->doc(MauSheetLienMinh::hanvina(), null, $tuyChon);
        $d = collect($kq['dong'])->keyBy(fn ($x) => mb_substr($x['ten_tour'], 0, 8).'|'.$x['ngay_di']);

        // Tab HCM có chú thích "Đỏ hết chỗ": chỉ ngày 24 (tô đỏ) là hết chỗ
        $this->assertSame('lien_he', $d['SUN_6N5D|2026-10-10']['trang_thai']);
        $this->assertSame('het_cho', $d['SUN_6N5D|2026-10-24']['trang_thai']);
        $this->assertSame('lien_he', $d['SUN_6N5D|2026-10-31']['trang_thai']);
        $this->assertSame('het_cho', $d['LỆ GIANG|2026-11-03']['trang_thai'], 'Cột không tiêu đề ghi FULL');

        // Tab HN tô đỏ ngày lễ (không có chú thích) → không phải hết chỗ;
        // "16/01 Đóng đoàn" là hạn chót, không phải hết chỗ; "Full" ở cột SL thì hết
        $this->assertSame('lien_he', $d['HÀ NỘI -|2026-12-29']['trang_thai']);
        $this->assertSame('lien_he', $d['HÀ NỘI -|2026-10-17']['trang_thai']);
        $this->assertSame('het_cho', $d['HÀ NỘI -|2026-11-03']['trang_thai']);

        // Tab không có dòng tiêu đề, đọc theo cột khai tay
        $this->assertSame([23590000, 1000000, 'lien_he'], [$d['NAM KINH|2026-10-31']['gia'], $d['NAM KINH|2026-10-31']['hoa_hong'], $d['NAM KINH|2026-10-31']['trang_thai']]);
        $this->assertSame('het_cho', $d['NAM KINH|2026-11-14']['trang_thai']);

        $baoCao = collect($kq['bao_cao'])->keyBy('tab');
        $this->assertTrue($baoCao['TOUR TRUNG QUỐC HCM']['mau_do']);
        $this->assertFalse($baoCao['TOUR TRUNG QUỐC HÀ NỘI']['mau_do']);
        $this->assertTrue($baoCao['BẮC KINH - THƯỢNG HẢI HCM']['cot_tay']);

        // Không bật tuỳ chọn màu đỏ → ngày đỏ vẫn là "liên hệ"
        $khong = collect(app(DocBangLienMinh::class)->doc(MauSheetLienMinh::hanvina())['dong']);
        $this->assertSame('lien_he', $khong->first(fn ($x) => $x['ngay_di'] === '2026-10-24' && str_starts_with($x['ten_tour'], 'SUN'))['trang_thai']);
    }

    public function test_doc_kieu_vna_phong_chu_dac_biet_va_com_trieu(): void
    {
        $d = collect(app(DocBangLienMinh::class)->doc(MauSheetLienMinh::vna())['dong'])->keyBy('ngay_di');

        $this->assertSame('SEOUL - BUSAN', $d['2026-11-13']['ten_tour'], 'Chữ 𝐒𝐄𝐎𝐔𝐋 đổi về chữ thường');
        $this->assertSame([20990000, 2500000, 4, 20, 'con_cho'], [$d['2026-11-13']['gia'], $d['2026-11-13']['hoa_hong'], $d['2026-11-13']['con_cho'], $d['2026-11-13']['tong_cho'], $d['2026-11-13']['trang_thai']]);
        $this->assertSame('OSBUS130526/06/TGH', $d['2026-11-13']['ma_tour']);
        $this->assertSame('30/10', $d['2026-11-13']['han_visa'], '"DEALINE VISA"');
        $this->assertSame([2000000, 'het_cho'], [$d['2026-11-20']['hoa_hong'], $d['2026-11-20']['trang_thai']], '"20.11", "2tr", "FULL"');
    }

    public function test_doc_kieu_j_travel_gia_khuyen_mai(): void
    {
        $d = collect(app(DocBangLienMinh::class)->doc(MauSheetLienMinh::jTravel())['dong'])->keyBy('ngay_di');

        $this->assertSame([23990000, 25990000], [$d['2026-10-12']['gia'], $d['2026-10-12']['gia_niem_yet']], 'Giá KM làm giá chính, giá gốc gạch ngang');
        $this->assertSame([27990000, null], [$d['2026-12-30']['gia'], $d['2026-12-30']['gia_niem_yet']]);
        $this->assertSame('CỬU TRẠI CÂU', $d['2026-10-12']['muc'], 'Cột THỊ TRƯỜNG là nhóm, không phải tên tour');
        $this->assertSame('THÀNH ĐÔ – TRÙNG KHÁNH – CỬU TRẠI CÂU', $d['2026-10-12']['ten_tour']);
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
            $nv->givePermissionTo(Permission::findOrCreate($q, 'web'));
        }
        $this->actingAs($nv);

        $tour = Tour::create(['slug' => 'seoul-'.uniqid(), 'name' => 'Seoul', 'type' => 'abroad', 'status' => 'published', 'adult_price' => 1]);
        $dot = TourDeparture::create(['tour_id' => $tour->id, 'start_date' => '2026-11-04', 'seats_total' => 30, 'seats_left' => 30]);

        Livewire::test(EditTour::class, ['record' => $tour->getRouteKey()])
            ->assertFormFieldExists('alliance_tour_id')
            ->fillForm(['alliance_tour_id' => $lm->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($lm->id, $tour->fresh()->alliance_tour_id);
        $this->assertSame(22, $dot->fresh()->seats_left, 'Nối xong là cập nhật ngay, không chờ 10 phút');

        Livewire::test(DeparturesRelationManager::class, [
            'ownerRecord' => $tour->fresh(),
            'pageClass' => EditTour::class,
        ])
            ->assertSee('Theo sheet: còn 22')
            ->callTableAction('layTuLienMinh');

        // Seoul trong sheet có 5 ngày sắp tới, web đã có 04/11 → thêm 4
        $this->assertSame(5, $tour->departures()->count());
        $this->assertSame(0, $tour->departures()->whereDate('start_date', '2026-10-28')->value('seats_left'));
    }

    public function test_dong_bo_luu_lich_tuan_gia_km_bao_cao_va_bo_qua_tab(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $nguon = AllianceSource::create(['name' => 'VVT', 'sheet_url' => self::LINK]);
        $this->traVe(MauSheetLienMinh::vvt());
        app(DongBoLienMinh::class)->dongBo($nguon);

        $tuan = AllianceDeparture::whereNull('departure_date')->get();
        $this->assertSame(['Thứ 5 hằng tuần', 'Thứ 5 hằng tuần', 'Thứ 3, 6, Chủ nhật hằng tuần'], $tuan->pluck('weekly')->all());
        $this->assertSame(['VL01', 'VL02', 'VL08'], $tuan->pluck('tour_code')->all());
        $baoCao = collect($nguon->fresh()->bao_cao)->keyBy('tab');
        $this->assertSame(3, $baoCao['TOUR NỘI ĐỊA']['so_ngay']);

        // Bảng tra chỗ hiện cả tour hằng tuần (kể cả khi lọc theo khoảng ngày)
        $this->actingAs($this->dieuHanh());
        Livewire::test(ListAllianceDepartures::class)
            ->assertSee('Thứ 3, 6, Chủ nhật hằng tuần')
            ->filterTable('ngay', ['tu' => '2026-12-01', 'den' => '2026-12-31'])
            ->assertCanSeeTableRecords($tuan)
            ->assertCanSeeTableRecords(AllianceDeparture::whereDate('departure_date', '2026-12-10')->get())
            ->assertCanNotSeeTableRecords(AllianceDeparture::whereDate('departure_date', '2027-01-08')->get());

        // Điều hành tắt tab nội địa → đọc lại thì các tour hằng tuần biến mất
        $nguon->update(['tab_bo_qua' => ['TOUR NỘI ĐỊA']]);
        app(DongBoLienMinh::class)->dongBo($nguon->fresh());
        $this->assertSame(0, AllianceDeparture::whereNull('departure_date')->count());
        $this->assertSame('bo_qua', collect($nguon->fresh()->bao_cao)->firstWhere('tab', 'TOUR NỘI ĐỊA')['tinh_trang']);
    }

    public function test_gia_khuyen_mai_luu_gia_goc(): void
    {
        $nguon = AllianceSource::create(['name' => 'J Travel', 'sheet_url' => self::LINK]);
        $this->traVe(MauSheetLienMinh::jTravel());
        app(DongBoLienMinh::class)->dongBo($nguon);

        $d = AllianceDeparture::whereDate('departure_date', '2026-10-12')->firstOrFail();
        $this->assertSame([23990000, 25990000], [$d->price, $d->price_original]);
    }

    public function test_them_link_hanvina_tu_dien_cau_hinh_san_va_trang_sua_hien_bao_cao(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $nguon = AllianceSource::create(['name' => 'Hanvina', 'sheet_url' => 'https://docs.google.com/spreadsheets/d/1iS3G9tbkfpE95csaYadKk4PZjY3dYJato1uKXSLLNGw/htmlview?gid=376037578']);
        $this->assertSame('theo_chu_thich', $nguon->mau_do);
        $this->assertSame('F', $nguon->cot_tay[0]['ngay_di']);
        $this->assertSame('BẮC KINH - THƯỢNG HẢI HCM', $nguon->cot_tay[0]['tab']);

        $this->traVe(MauSheetLienMinh::hanvina());
        app(DongBoLienMinh::class)->dongBo($nguon);
        $this->assertTrue(AllianceDeparture::whereDate('departure_date', '2026-10-24')->where('status', 'het_cho')->exists(), 'Ngày tô đỏ = hết chỗ');

        $this->actingAs($this->dieuHanh());
        Livewire::test(EditAllianceSource::class, ['record' => $nguon->getRouteKey()])
            ->assertOk()
            ->assertFormFieldExists('mau_do')
            ->assertFormFieldExists('tab_bo_qua')
            ->assertSee('ngày tô đỏ = hết chỗ')
            ->assertSee('đọc theo cột khai tay');

        // Sheet không có trong cấu hình khai sẵn → mặc định không coi màu đỏ là hết chỗ
        $this->assertSame('tat', AllianceSource::create(['name' => 'Khác', 'sheet_url' => self::LINK])->fresh()->mau_do);
    }

    public function test_nhan_vien_khong_co_quyen_khong_vao_duoc(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $nv = User::factory()->create();
        $nv->givePermissionTo(Permission::findOrCreate('ViewAny:Booking', 'web'));
        $this->actingAs($nv);

        $this->get('/admin/tra-cho-lien-minh')->assertForbidden();
        $this->get('/admin/alliance-sources')->assertForbidden();
    }

    // ------------------------------------------------------------------
    // Tin nhắn tình trạng chỗ (gửi sale / khách, không ghi tên đối tác)
    // ------------------------------------------------------------------

    private function tourThuongHai(): AllianceTour
    {
        $this->travelTo(Carbon::parse('2026-10-08 09:00'));
        $nguon = AllianceSource::create(['name' => 'Kim Liên Travel', 'sheet_url' => self::LINK, 'last_success_at' => now()]);
        $tour = AllianceTour::create([
            'alliance_source_id' => $nguon->id, 'key' => 'thuong-hai',
            'name' => 'Thượng Hải - Ô Trấn - Hàng Châu (No Shop) - Kim Liên Travel',
            'duration' => '5N5Đ',
            'airline' => "Sun PhuQuoc Airways (9G)\n9G634 SGN-PVG 23:40-04:40\n9G635 PVG-SGN\n05:40-09:40",
        ]);
        $dot = fn (string $ngay, array $them) => AllianceDeparture::create([
            'alliance_tour_id' => $tour->id, 'alliance_source_id' => $nguon->id, 'departure_date' => $ngay,
            'status' => 'con_cho', 'price' => 13_990_000, 'commission' => 1_500_000, ...$them,
        ]);
        $dot('2026-10-01', ['seats_left' => 9]);                                        // đã qua
        $dot('2026-10-17', ['seats_left' => 0, 'seats_sold' => 33, 'status' => 'het_cho', 'visa_deadline' => '07/10/2026']);
        $dot('2026-10-21', ['seats_left' => 0, 'seats_hold' => 6, 'seats_sold' => 22, 'status' => 'het_cho', 'visa_deadline' => '10/10/2026']);
        $dot('2026-10-24', ['seats_left' => 16, 'seats_sold' => 8]);
        $dot('2026-10-26', ['seats_left' => 20, 'status' => 'huy']);                   // đoàn huỷ
        $dot('2026-11-04', ['seats_left' => 14, 'seats_hold' => 5, 'seats_sold' => 5, 'price' => 15_990_000]);
        $dot('2026-11-07', ['seats_left' => 23, 'seats_sold' => 1, 'price' => 15_990_000]);
        $dot('2026-12-23', ['seats_left' => 22, 'seats_hold' => 2, 'price' => 15_990_000, 'note' => 'NOEL']);
        $dot('2026-12-30', ['seats_left' => 20, 'seats_hold' => 4, 'price' => 17_990_000, 'note' => 'TẾT TÂY — Kim Liên Travel giữ']);
        $dot('2027-01-02', ['status' => 'lien_he', 'price' => null, 'price_text' => 'Liên hệ']);

        return $tour;
    }

    public function test_soan_tin_nhan_tinh_trang_cho_khong_ghi_ten_doi_tac(): void
    {
        $tin = SoanTinLienMinh::tour($this->tourThuongHai());

        $this->assertSame(<<<'TIN'
THƯỢNG HẢI - Ô TRẤN - HÀNG CHÂU (NO SHOP) UPDATE 8/10/2026
5N5Đ • Sun PhuQuoc Airways (9G)
9G634 SGN-PVG 23:40-04:40 / 9G635 PVG-SGN 05:40-09:40

Tháng 10:
🌳 17/10 - 21/10: NHẬN 0S SURE 33S
🌳 21/10 - 25/10: NHẬN 0S HOLD 6S SURE 22S
🌳 24/10 - 28/10: NHẬN 16S SURE 8S
ĐỒNG GIÁ 13.990

Tháng 11:
🌳 04/11 - 08/11: NHẬN 14S HOLD 5S SURE 5S
🌳 07/11 - 11/11: NHẬN 23S SURE 1S
ĐỒNG GIÁ 15.990

Tháng 12:
🌳 23/12 - 27/12: NHẬN 22S HOLD 2S (NOEL)
GIÁ 15.990
🌳 30/12 - 03/01: NHẬN 20S HOLD 4S (TẾT TÂY giữ)
GIÁ 17.990

Tháng 1/2027:
🌳 02/01 - 06/01: LIÊN HỆ
GIÁ Liên hệ

DEADLINE VISA THÁNG 10
🌳 Đoàn 17/10: deadline visa 07/10/2026
🌳 Đoàn 21/10: deadline visa 10/10/2026
TIN, $tin);
        $this->assertStringNotContainsStringIgnoringCase('Kim Liên', $tin);
        $this->assertStringNotContainsString('1.500', $tin); // không lộ hoa hồng
    }

    public function test_nut_tin_nhan_o_tra_cho_lien_minh(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $tour = $this->tourThuongHai();
        $this->actingAs($this->dieuHanh());
        $ngay24 = AllianceDeparture::whereDate('departure_date', '2026-10-24')->sole();
        $ngay04 = AllianceDeparture::whereDate('departure_date', '2026-11-04')->sole();

        // Từng tour: mọi ngày đi sắp tới
        Livewire::test(ListAllianceDepartures::class)
            ->mountTableAction('tinNhanTour', $ngay24)
            ->assertSet('mountedActions.0.data.tin', fn ($v) => str_contains($v, '17/10 - 21/10') && str_contains($v, '30/12 - 03/01'));

        // Chọn vài ngày: chỉ những ngày đó
        Livewire::test(ListAllianceDepartures::class)
            ->mountTableBulkAction('tinNhanDaChon', [$ngay24, $ngay04])
            ->assertSet('mountedActions.0.data.tin', fn ($v) => str_contains($v, '24/10 - 28/10') && str_contains($v, '04/11 - 08/11')
                && ! str_contains($v, '17/10') && ! str_contains($v, 'Kim Liên'));
    }

    public function test_doc_hang_bay_va_so_ngay(): void
    {
        $this->assertSame(['VNA', null], SoanTinLienMinh::hangBay('VNA'));
        $this->assertSame([null, 'VJ862 HCM - INC 02:35 - 09:40 / VJ861 INC - HCM 21:20 - 00:30+1'],
            SoanTinLienMinh::hangBay("Chuyến đi\nVJ862 HCM - INC 02:35 - 09:40\nChuyến về\nVJ861 INC - HCM\n21:20 - 00:30+1"));
        $this->assertSame(['Sichuan Airlines + 2 chiều tàu cao tốc 3U', null], SoanTinLienMinh::hangBay("Sichuan Airlines + 2 chiều tàu cao tốc\n3U"));
        $this->assertSame(5, SoanTinLienMinh::soNgay('5N4Đ'));
        $this->assertSame(6, SoanTinLienMinh::soNgay('06N05Đ'));
        $this->assertSame(4, SoanTinLienMinh::soNgay('4 ngày'));
        $this->assertNull(SoanTinLienMinh::soNgay('THÁNG 2'));
        $this->assertSame('13.990', SoanTinLienMinh::nghin(13_990_000));
        // Tên đối tác ngắn chỉ xoá khi đứng riêng
        $this->assertSame('KAZAN - Tour', SoanTinLienMinh::boTenDoiTac('KAZAN - Tour - AZ', 'AZ'));
    }
}
