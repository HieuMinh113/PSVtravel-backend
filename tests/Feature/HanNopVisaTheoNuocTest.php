<?php

namespace Tests\Feature;

use App\Filament\Resources\Tours\Pages\EditTour;
use App\Filament\Resources\Tours\RelationManagers\DeparturesRelationManager;
use App\Filament\Resources\VisaDeadlines\Pages\CreateVisaDeadline;
use App\Filament\Resources\VisaDeadlines\Pages\EditVisaDeadline;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Modules\Category\Models\Category;
use Modules\Tour\Models\Tour;
use Modules\Tour\Models\TourDeparture;
use Modules\Tour\Services\HanNopVisa;
use Modules\Visa\Database\Seeders\QuyenVisaSeeder;
use Modules\Visa\Models\VisaDeadline;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Hạn nộp visa theo nước (nhân viên visa quản lý): "Trung Quốc 7 ngày", bay
 * ngày 22 → từ ngày 15 đợt đó tự ẩn trên website, khách không đặt online được;
 * tour mà mọi đợt sắp tới đều quá hạn thì ẩn khỏi danh sách.
 */
class HanNopVisaTheoNuocTest extends TestCase
{
    use RefreshDatabase;

    private Tour $tour;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-01 09:00'));
        $this->tour = $this->taoTour('bac-kinh', 'Trung Quốc');
    }

    private function taoTour(string $slug, ?string $nuoc, array $them = []): Tour
    {
        return Tour::create(['slug' => $slug, 'name' => 'Tour '.$slug, 'type' => 'abroad', 'status' => 'published', 'country' => $nuoc,
            'adult_price' => 10_000_000, 'duration_days' => 5, 'duration_nights' => 4, ...$them]);
    }

    private function dot(string $ngay, ?Tour $tour = null, array $them = []): TourDeparture
    {
        return TourDeparture::create(['tour_id' => ($tour ?? $this->tour)->id, 'start_date' => $ngay, 'seats_total' => 20, 'seats_left' => 20, ...$them]);
    }

    private function slugTrongDanhSach(): array
    {
        return array_column($this->getJson('/api/v1/tours')->assertOk()->json('data'), 'slug');
    }

    public function test_nhan_vien_visa_quan_ly_danh_sach_nhan_vien_khac_khong(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $visa = User::factory()->create()->assignRole('visa');
        $this->actingAs($visa);
        $this->get('/admin/visa-deadlines')->assertOk()->assertSee('Hạn nộp visa theo nước');
        $this->get('/admin/visa-deadlines/create')->assertOk();

        Livewire::test(CreateVisaDeadline::class)
            ->fillForm(['country' => 'Trung Quốc', 'days' => 7])
            ->assertSee('Áp dụng cho 1 tour: Tour bac-kinh')
            ->call('create')
            ->assertHasNoFormErrors();
        $this->assertSame(7, VisaDeadline::sole()->days);

        // Trùng tên kể cả khác dấu / chữ hoa
        Livewire::test(CreateVisaDeadline::class)
            ->fillForm(['country' => 'trung quoc', 'days' => 10])
            ->call('create')
            ->assertHasFormErrors(['country']);
        // Số ngày phải hợp lệ
        Livewire::test(CreateVisaDeadline::class)
            ->fillForm(['country' => 'Nhật Bản', 'days' => 0])
            ->call('create')
            ->assertHasFormErrors(['days']);

        Livewire::test(EditVisaDeadline::class, ['record' => VisaDeadline::sole()->getRouteKey()])
            ->fillForm(['days' => 10])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(10, VisaDeadline::sole()->days);

        foreach (['sale', 'marketing', 'dieu_hanh', 'ke_toan'] as $vaiTro) {
            $this->actingAs(User::factory()->create()->assignRole($vaiTro));
            $this->assertSame(403, $this->get('/admin/visa-deadlines')->status(), $vaiTro);
        }
        // admin / super_admin cũng được cấp (máy mới: RoleSeeder tạo vai trò rồi gọi lại seeder quyền visa)
        Role::findOrCreate('admin', 'web');
        (new QuyenVisaSeeder)->run();
        $admin = User::factory()->create()->assignRole('admin');
        $this->assertTrue($admin->can('viewAny', VisaDeadline::class) && $admin->can('create', VisaDeadline::class));
    }

    public function test_bay_ngay_22_han_7_ngay_tu_ngay_15_an_tren_website(): void
    {
        VisaDeadline::create(['country' => 'Trung Quốc', 'days' => 7]);
        $dot22 = $this->dot('2026-11-22');
        $this->assertSame('2026-11-14', $dot22->visa_last_day->toDateString());
        $category = Category::create(['type' => 'abroad', 'name' => 'Trung Quốc', 'slug' => 'trung-quoc', 'status' => 'published']);
        $this->tour->categories()->attach($category);

        // Ngày 14: vẫn hiện, hạn nộp là 14/11 (hôm nay là hạn cuối)
        $this->travelTo(Carbon::parse('2026-11-14 23:00'));
        $this->assertContains('bac-kinh', $this->slugTrongDanhSach());
        $r = $this->getJson('/api/v1/tours/bac-kinh')->assertOk();
        $this->assertSame(['2026-11-22'], array_column($r->json('data.departures'), 'start_date'));
        $this->assertSame('2026-11-14', $r->json('data.departures.0.visa_deadline'));
        $this->assertFalse($r->json('data.het_han_visa'));
        $this->assertSame(1, collect($this->getJson('/api/v1/categories')->json('data'))->firstWhere('slug', 'trung-quoc')['tours_count']);

        // Ngày 15: đợt 22/11 ẩn → tour không còn đợt nào → ẩn khỏi danh sách
        $this->travelTo(Carbon::parse('2026-11-15 00:05'));
        $this->assertNotContains('bac-kinh', $this->slugTrongDanhSach());
        $r = $this->getJson('/api/v1/tours/bac-kinh')->assertOk(); // link cũ vẫn mở được
        $this->assertSame([], $r->json('data.departures'));
        $this->assertTrue($r->json('data.het_han_visa'));
        $this->assertSame(0, collect($this->getJson('/api/v1/categories')->json('data'))->firstWhere('slug', 'trung-quoc')['tours_count']);

        // Khách mở trang từ trước rồi bấm đặt → từ chối
        $this->postJson('/api/v1/bookings', [
            'tour_id' => $this->tour->id, 'tour_departure_id' => $dot22->id,
            'customer_name' => 'Nguyễn Văn A', 'customer_phone' => '0909000111', 'adults' => 2,
        ])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'hết hạn nhận hồ sơ visa'));

        // Có thêm đợt xa hơn → tour hiện lại, chỉ kể đợt còn hạn
        $this->dot('2026-12-20');
        $this->assertContains('bac-kinh', $this->slugTrongDanhSach());
        $the = collect($this->getJson('/api/v1/tours')->json('data'))->firstWhere('slug', 'bac-kinh');
        $this->assertSame(['2026-12-20'], $the['departure_dates']);
        $this->assertSame('20/12/2026', $the['next_start_date']);
        $r = $this->getJson('/api/v1/tours/bac-kinh');
        $this->assertSame(['2026-12-20'], array_column($r->json('data.departures'), 'start_date'));
        $this->assertFalse($r->json('data.het_han_visa'));
    }

    public function test_lay_han_som_nhat_giua_nuoc_tour_va_han_rieng(): void
    {
        VisaDeadline::create(['country' => 'Trung Quốc', 'days' => 7]);
        $this->assertSame('2026-11-14', $this->dot('2026-11-22')->hanVisa()->toDateString()); // theo nước

        $this->tour->update(['visa_deadline_days' => 10]);
        $this->assertSame('2026-11-13', $this->dot('2026-11-23')->hanVisa()->toDateString()); // quy tắc tour sớm hơn
        $this->assertSame('2026-11-12', TourDeparture::orderBy('start_date')->first()->visa_last_day->toDateString()); // đợt cũ được tính lại

        $this->assertSame('2026-11-10', $this->dot('2026-11-24', null, ['visa_deadline' => '2026-11-10'])->hanVisa()->toDateString());
        $this->assertSame('2026-11-15', $this->dot('2026-11-25', null, ['visa_deadline' => '2026-11-20'])->hanVisa()->toDateString());
        $this->assertSame('Theo nước: Trung Quốc 7 ngày', HanNopVisa::nguonHan($this->dot('2026-12-22'), $this->tour->fresh()->forceFill(['visa_deadline_days' => 3])));
    }

    public function test_ten_nuoc_khop_khong_dau_va_tour_nhieu_nuoc_lay_so_lon_nhat(): void
    {
        VisaDeadline::create(['country' => 'Pháp', 'days' => 20]);
        VisaDeadline::create(['country' => 'Ý', 'days' => 30]);
        VisaDeadline::create(['country' => 'Hàn Quốc', 'days' => 12]);
        VisaDeadline::create(['country' => 'Châu Âu', 'days' => 25]);

        $this->assertSame(30, HanNopVisa::soNgayTheoNuoc('Pháp - Thụy Sĩ - Ý'));
        $this->assertSame(20, HanNopVisa::soNgayTheoNuoc('PHAP, Thuy Si'));
        $this->assertSame(12, HanNopVisa::soNgayTheoNuoc('hàn quốc'));
        $this->assertSame(12, HanNopVisa::soNgayTheoNuoc('Hàn Quốc và Nhật Bản'));
        $this->assertSame(25, HanNopVisa::soNgayTheoNuoc('Châu Âu (Schengen)'));
        $this->assertNull(HanNopVisa::soNgayTheoNuoc('Nhật Bản'));
        $this->assertNull(HanNopVisa::soNgayTheoNuoc(null));

        $chauAu = $this->taoTour('tay-au', 'Pháp - Thụy Sĩ - Ý');
        $this->assertSame('2026-11-30', $this->dot('2026-12-31', $chauAu)->visa_last_day->toDateString()); // 31/12 − 31
    }

    public function test_doi_danh_sach_hoac_doi_quoc_gia_tour_thi_tinh_lai(): void
    {
        $dot = $this->dot('2026-11-22');
        $this->assertNull($dot->fresh()->visa_last_day);

        $han = VisaDeadline::create(['country' => 'Trung Quốc', 'days' => 7]);
        $this->assertSame('2026-11-14', $dot->fresh()->visa_last_day->toDateString());

        $han->update(['days' => 10]);
        $this->assertSame('2026-11-11', $dot->fresh()->visa_last_day->toDateString());

        $this->tour->update(['country' => 'Nhật Bản']);
        $this->assertNull($dot->fresh()->visa_last_day);
        $this->tour->update(['country' => 'Trung Quốc (Bắc Kinh)']);
        $this->assertSame('2026-11-11', $dot->fresh()->visa_last_day->toDateString());

        $han->delete();
        $this->assertNull($dot->fresh()->visa_last_day);
    }

    public function test_tour_chua_co_lich_va_tour_trong_nuoc_khong_bi_anh_huong(): void
    {
        VisaDeadline::create(['country' => 'Trung Quốc', 'days' => 7]);
        VisaDeadline::create(['country' => 'Việt Nam', 'days' => 7]);
        $this->taoTour('trung-quoc-theo-yeu-cau', 'Trung Quốc'); // chưa có lịch: bán theo yêu cầu
        $trongNuoc = $this->taoTour('da-nang', 'Việt Nam', ['type' => 'domestic']);
        $this->dot('2026-11-05', $trongNuoc);
        $daDi = $this->taoTour('tour-da-di', 'Trung Quốc');
        $this->dot('2026-10-20', $daDi); // chỉ có đợt đã đi: như tour chưa có lịch

        $this->dot('2026-11-05'); // bac-kinh: quá hạn (hạn 28/10)

        $ds = $this->slugTrongDanhSach();
        $this->assertContains('trung-quoc-theo-yeu-cau', $ds);
        $this->assertContains('da-nang', $ds);
        $this->assertContains('tour-da-di', $ds);
        $this->assertNotContains('bac-kinh', $ds);
        $this->assertFalse($this->getJson('/api/v1/tours/trung-quoc-theo-yeu-cau')->json('data.het_han_visa'));
        $this->assertSame(['2026-11-05'], array_column($this->getJson('/api/v1/tours/da-nang')->json('data.departures'), 'start_date'));

        // Đợt đã đóng / hết chỗ không tính là "còn đợt sắp tới"
        $this->dot('2026-12-01', $daDi, ['status' => 'closed']);
        $this->assertContains('tour-da-di', $this->slugTrongDanhSach());
    }

    public function test_admin_thay_dot_an_tren_web_va_goi_y_o_quoc_gia(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        VisaDeadline::create(['country' => 'Trung Quốc', 'days' => 7]);
        $this->dot('2026-11-05');
        $this->dot('2026-12-20');
        $nv = User::factory()->create();
        foreach (['View', 'ViewAny', 'Update', 'Create'] as $q) {
            $nv->givePermissionTo(Permission::findOrCreate("{$q}:Tour", 'web'), Permission::findOrCreate("{$q}:TourDeparture", 'web'));
        }
        $this->actingAs($nv);

        Livewire::test(DeparturesRelationManager::class, ['ownerRecord' => $this->tour, 'pageClass' => EditTour::class])
            ->assertSee('Ẩn trên web (quá hạn visa)')
            ->assertSee('Theo nước: Trung Quốc 7 ngày')
            ->assertSee('Còn nhận');

        Livewire::test(EditTour::class, ['record' => $this->tour->getRouteKey()])
            ->assertSee('Hạn nộp visa theo nước: Trung Quốc — nộp trước ngày bay 7 ngày')
            ->fillForm(['country' => 'Mông Cổ'])
            ->assertSee('chưa có trong danh sách');
    }
}
