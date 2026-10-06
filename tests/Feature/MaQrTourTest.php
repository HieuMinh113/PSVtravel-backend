<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MaQrTour;
use App\Filament\Resources\Tours\Pages\ListTours;
use App\Filament\Resources\Tours\Pages\ViewTour;
use chillerlan\QRCode\QRCode;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tour\Models\Tour;
use Modules\Tour\Models\TourQrScan;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Mã QR từng tour: tạo mã có logo vẫn quét ra đúng link, đếm lượt quét đúng,
 * chuyển khách tới đúng trang, và chỉ người có quyền mới tải được mã.
 */
class MaQrTourTest extends TestCase
{
    use RefreshDatabase;

    private const DT = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148';

    private function tour(array $them = []): Tour
    {
        return Tour::create(array_merge([
            'slug' => 'da-nang-hoi-an-'.uniqid(),
            'name' => 'Đà Nẵng – Hội An',
            'type' => 'domestic',
            'status' => 'published',
            'adult_price' => 5990000,
        ], $them));
    }

    public function test_ma_png_co_logo_van_doc_ra_dung_link(): void
    {
        $tour = $this->tour();
        $png = app(MaQrTour::class)->png($tour);

        $this->assertStringStartsWith("\x89PNG", $png);
        [$rong, $cao] = getimagesizefromstring($png);
        $this->assertSame($rong, $cao);
        $this->assertGreaterThanOrEqual(1000, $rong);

        // Đọc ngược mã bằng một thư viện QR KHÁC (chillerlan) — đúng như điện
        // thoại khách quét: logo ở giữa không được làm hỏng mã.
        $doc = (string) (new QRCode)->readFromBlob($png);
        $this->assertSame(MaQrTour::duongDan($tour), $doc);
        $this->assertStringEndsWith('/qr/'.$tour->id, $doc);
    }

    public function test_ma_svg_hop_le_co_mau_thuong_hieu_va_logo(): void
    {
        $svg = app(MaQrTour::class)->svg($this->tour());

        $xml = simplexml_load_string($svg);
        $this->assertNotFalse($xml, 'SVG phải là XML hợp lệ');
        $this->assertStringContainsString(MaQrTour::MAU, $svg);
        $this->assertStringContainsString('data:image/png;base64,', $svg);
    }

    public function test_quet_tour_dang_ban_chuyen_toi_trang_tour_va_ghi_luot(): void
    {
        $tour = $this->tour();

        $this->withHeaders(['User-Agent' => self::DT])
            ->postJson("/api/v1/qr/{$tour->id}")
            ->assertOk()
            ->assertJson(['url' => "/tour-trong-nuoc/{$tour->slug}"]);

        $luot = TourQrScan::where('tour_id', $tour->id)->get();
        $this->assertCount(1, $luot);
        $this->assertSame('dien_thoai', $luot[0]->thiet_bi);
    }

    public function test_quet_lai_lien_tuc_chi_tinh_mot_luot(): void
    {
        $tour = $this->tour();
        for ($i = 0; $i < 3; $i++) {
            $this->withHeaders(['User-Agent' => self::DT])->postJson("/api/v1/qr/{$tour->id}")->assertOk();
        }

        $this->assertSame(1, TourQrScan::where('tour_id', $tour->id)->count());
    }

    public function test_bot_xem_truoc_link_khong_tinh_luot_nhung_van_chuyen_trang(): void
    {
        $tour = $this->tour(['type' => 'abroad']);

        $this->withHeaders(['User-Agent' => 'facebookexternalhit/1.1'])
            ->postJson("/api/v1/qr/{$tour->id}")
            ->assertOk()
            ->assertJson(['url' => "/tour-nuoc-ngoai/{$tour->slug}"]);

        $this->assertSame(0, TourQrScan::count());
    }

    public function test_tour_da_an_hoac_da_xoa_chuyen_ve_danh_sach(): void
    {
        $an = $this->tour(['status' => 'hidden']);
        $xoa = $this->tour(['type' => 'abroad']);
        $xoa->delete();

        $this->withHeaders(['User-Agent' => self::DT])->postJson("/api/v1/qr/{$an->id}")
            ->assertOk()->assertJson(['url' => '/tour-trong-nuoc']);
        $this->withHeaders(['User-Agent' => self::DT])->postJson("/api/v1/qr/{$xoa->id}")
            ->assertOk()->assertJson(['url' => '/tour-nuoc-ngoai']);
    }

    public function test_ma_khong_ton_tai_tra_404_kem_trang_chu(): void
    {
        $this->postJson('/api/v1/qr/999999')->assertNotFound()->assertJson(['url' => '/']);
    }

    public function test_thong_ke_luot_quet(): void
    {
        $tour = $this->tour();
        TourQrScan::create(['tour_id' => $tour->id, 'thiet_bi' => 'dien_thoai', 'scanned_at' => now()]);
        TourQrScan::create(['tour_id' => $tour->id, 'thiet_bi' => 'may_tinh', 'scanned_at' => now()->subDays(10)]);
        TourQrScan::create(['tour_id' => $tour->id, 'thiet_bi' => 'dien_thoai', 'scanned_at' => now()->subDays(40)]);

        $tk = MaQrTour::thongKe($tour);
        $this->assertSame(3, $tk['tong']);
        $this->assertSame(1, $tk['hom_nay']);
        $this->assertSame(1, $tk['bay_ngay']);
        $this->assertSame(2, $tk['ba_muoi_ngay']);
        $this->assertSame(2, $tk['dien_thoai']);
    }

    public function test_tai_ma_qr_can_dang_nhap_va_quyen_xem_tour(): void
    {
        $tour = $this->tour();
        $png = route('filament.admin.tours.ma-qr', ['tour' => $tour, 'dinhDang' => 'png']);
        $svg = route('filament.admin.tours.ma-qr', ['tour' => $tour, 'dinhDang' => 'svg']);

        // Chưa đăng nhập → về trang đăng nhập
        $this->get($png)->assertRedirect();

        // Có quyền vào admin nhưng KHÔNG có quyền xem tour → bị chặn
        $khongQuyen = User::factory()->create();
        $khongQuyen->givePermissionTo(Permission::findOrCreate('ViewAny:Booking', 'web'));
        $this->actingAs($khongQuyen)->get($png)->assertForbidden();

        // Có quyền xem tour → tải được cả hai định dạng
        $coQuyen = User::factory()->create();
        $coQuyen->givePermissionTo(Permission::findOrCreate('View:Tour', 'web'));
        $this->actingAs($coQuyen)->get($png)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Content-Disposition', 'attachment; filename="ma-qr-'.$tour->slug.'.png"');
        $this->actingAs($coQuyen)->get($svg)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml');
    }

    public function test_trang_quan_tri_mo_duoc_modal_ma_qr_va_cot_luot_quet(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $tour = $this->tour();
        TourQrScan::create(['tour_id' => $tour->id, 'thiet_bi' => 'dien_thoai', 'scanned_at' => now()]);

        $nv = User::factory()->create();
        $nv->givePermissionTo(
            Permission::findOrCreate('View:Tour', 'web'),
            Permission::findOrCreate('ViewAny:Tour', 'web'),
        );
        $this->actingAs($nv);

        Livewire::test(ViewTour::class, ['record' => $tour->getRouteKey()])
            ->mountAction('maQr')
            ->assertActionMounted('maQr')
            ->assertMountedActionModalSee(['Tải PNG', 'Tải SVG', '/qr/'.$tour->id, 'Hôm nay'])
            ->assertMountedActionModalSeeHtml('data:image/svg+xml;base64,');

        Livewire::test(ListTours::class)
            ->assertCanSeeTableRecords([$tour])
            ->assertTableColumnStateSet('qr_scans_count', 1, $tour);
    }
}
