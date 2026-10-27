<?php

namespace Tests\Feature;

use App\Filament\Resources\MaldivesBookings\Pages\CreateMaldivesBooking;
use App\Filament\Resources\MaldivesBookings\Pages\ListMaldivesBookings;
use App\Filament\Resources\MaldivesCombos\Pages\CreateMaldivesCombo;
use App\Filament\Resources\MaldivesCombos\Pages\EditMaldivesCombo;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Maldives\Models\MaldivesBooking;
use Modules\Maldives\Models\MaldivesCombo;
use Modules\Maldives\Models\MaldivesResort;
use Modules\Page\Models\Setting;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Combo Maldives (tách riêng khỏi tour): giá USD theo hạng phòng × ngày, trẻ em
 * theo %, phụ thu theo nơi khởi hành, đơn đặt từ website và trong admin.
 * Số liệu lấy theo file Sun Siyam World 5* – bay SQ.
 */
class MaldivesTest extends TestCase
{
    use RefreshDatabase;

    private MaldivesCombo $combo;

    /** @var array<string, int> tên hạng phòng → id */
    private array $phong = [];

    protected function setUp(): void
    {
        parent::setUp();
        $resort = MaldivesResort::create(['name' => 'Sun Siyam World', 'stars' => 5, 'atoll' => 'Noonu Atoll', 'status' => 'published',
            'intro' => "Đoạn một.\n\nĐoạn hai.", 'amenities' => [['title' => 'Giải trí', 'items' => ['Live music và DJ', 'Karaoke nights']], ['title' => 'Trống', 'items' => []]],
            'policies' => [['title' => 'Nhận phòng – trả phòng', 'content' => 'Nhận phòng 14:00, trả phòng 12:00.']]]);
        foreach (['Water Villa with Pool & Slide' => '3 người lớn hoặc 2 người lớn + 1 trẻ em', 'Ocean Villa with Pool & Slide' => '3 người lớn hoặc 2 người lớn'] as $ten => $suc) {
            $this->phong[$ten] = $resort->roomTypes()->create(['name' => $ten, 'capacity' => $suc])->id;
        }
        $this->combo = MaldivesCombo::create(['resort_id' => $resort->id, 'name' => 'Sun Siyam World 5* – bay Singapore Airlines', 'status' => 'published',
            'duration_days' => 5, 'duration_nights' => 4, 'airline' => 'Singapore Airlines', 'transfer' => 'Thuỷ phi cơ', 'child_percent' => 75,
            'departure_cities' => [
                ['city' => 'Hà Nội', 'surcharge_usd' => 0, 'flights' => "SQ 191 HANSIN 1235 1705\nSQ 438 SINMLE 2045 2210"],
                ['city' => 'TP.HCM', 'surcharge_usd' => 100, 'flights' => 'SQ 183 SGNSIN 1555 1855'],
                ['city' => 'Đà Nẵng', 'surcharge_usd' => null, 'flights' => ''],
            ],
            'included' => ['Vé máy bay khứ hồi Singapore Airlines', '02 đêm Resort Siyam World 5*'], 'excluded' => ['Chi phí cá nhân']]);
        $w = $this->phong['Water Villa with Pool & Slide'];
        $o = $this->phong['Ocean Villa with Pool & Slide'];
        $this->combo->dates()->createMany([
            ['start_date' => now()->addDays(10), 'prices' => [$w => 2631, $o => 2846], 'status' => 'open'],
            ['start_date' => now()->addDays(100), 'label' => 'Lịch Tết', 'prices' => [$w => 2817, $o => null], 'status' => 'open'],
            ['start_date' => now()->addDays(150), 'prices' => [$w => 2500], 'status' => 'full'],
            ['start_date' => now()->subDays(3), 'prices' => [$w => 1000], 'status' => 'open'],
        ]);
        $this->combo->days()->createMany([
            ['day_number' => 1, 'title' => 'Việt Nam – khách sạn ở Male', 'meals' => 'Bữa tối trên máy bay', 'description' => '10h00: Có mặt tại sân bay.'],
            ['day_number' => 2, 'title' => 'Siyam World 5*', 'meals' => 'Bữa sáng, trưa, tối, đồ uống', 'description' => 'Thuỷ phi cơ ra resort.'],
        ]);
        Setting::query()->updateOrCreate(['key' => 'maldives_ty_gia'], ['value' => '26.300', 'group' => 'general']);
    }

    public function test_website_doc_danh_sach_va_chi_tiet_combo(): void
    {
        MaldivesCombo::create(['resort_id' => $this->combo->resort_id, 'name' => 'Combo nháp', 'status' => 'draft']);

        $ds = $this->getJson('/api/v1/maldives')->assertOk()->json('data');
        $this->assertCount(1, $ds);
        // Giá từ: ngày sắp tới còn nhận khách — bỏ ngày đã qua ($1000) và ngày hết phòng ($2500)
        $this->assertSame(2631, $ds[0]['gia_tu_usd']);
        $this->assertSame(['Hà Nội', 'TP.HCM', 'Đà Nẵng'], $ds[0]['noi_khoi_hanh']);
        $this->assertCount(2, $ds[0]['ngay_khoi_hanh']);

        $ct = $this->getJson('/api/v1/maldives/'.$this->combo->slug)->assertOk()->json('data');
        $this->assertSame(['SQ 191 HANSIN 1235 1705', 'SQ 438 SINMLE 2045 2210'], $ct['noi_khoi_hanh'][0]['flights']);
        $this->assertNull($ct['noi_khoi_hanh'][2]['surcharge_usd']);
        $this->assertCount(3, $ct['dates']); // ngày hết phòng vẫn hiện, ngày đã qua thì không
        $this->assertSame([$this->phong['Water Villa with Pool & Slide'] => 2817], $ct['dates'][1]['prices']);
        $this->assertSame(['Giải trí'], array_column($ct['resort']['amenities'], 'title')); // nhóm trống không hiện
        $this->assertSame(['Việt Nam – khách sạn ở Male', 'Siyam World 5*'], array_column($ct['days'], 'title'));
        $this->assertCount(2, $ct['room_types']);

        // Resort tạm ẩn → combo ẩn theo
        $this->combo->resort->update(['status' => 'hidden']);
        $this->getJson('/api/v1/maldives/'.$this->combo->slug)->assertNotFound();
        $this->assertSame([], $this->getJson('/api/v1/maldives')->json('data'));
    }

    public function test_dat_combo_tu_tinh_gia_va_bao_nhan_vien(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class); // gửi nhiều lượt liền nhau để thử từng trường hợp
        $this->seed(RoleSeeder::class);
        $sale = User::factory()->create()->assignRole('sale');
        [$ngay, $tet, $het] = $this->combo->dates()->orderBy('start_date')->where('start_date', '>=', today())->get()->all();
        $w = $this->phong['Water Villa with Pool & Slide'];
        $gui = fn (array $them) => $this->postJson('/api/v1/maldives/bookings', [
            'combo_id' => $this->combo->id, 'customer_name' => 'Nguyễn Văn A', 'customer_phone' => '0901234567', 'adults' => 2, ...$them,
        ]);

        // 2 người lớn + 1 trẻ em (75%) từ TP.HCM (phụ thu $100/khách)
        $kq = $gui(['combo_date_id' => $ngay->id, 'room_type_id' => $w, 'departure_city' => 'TP.HCM', 'children' => 1,
            'nguon' => ['utm_source' => 'facebook']])->assertCreated()->json('data');
        $this->assertSame(2 * 2631 + 1973 + 3 * 100, $kq['total_usd']);
        $this->assertSame(7535 * 26300, $kq['total_vnd']);
        $don = MaldivesBooking::firstWhere('code', $kq['code']);
        $this->assertSame(['pending', 'web', 'Water Villa with Pool & Slide', 100], [$don->status, $don->source, $don->room_type_name, $don->surcharge_usd]);
        $this->assertNull($don->assigned_to);
        // Nhân viên có quyền xử lý đơn Maldives nhận chuông
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $sale->id)->count());

        // Phụ thu chưa có số / em bé: không cộng, báo "chưa gồm"
        $kq = $gui(['combo_date_id' => $ngay->id, 'room_type_id' => $w, 'departure_city' => 'Đà Nẵng', 'infants' => 1])->assertCreated()->json('data');
        $this->assertSame(2 * 2631, $kq['total_usd']);
        $this->assertSame(['phụ thu khởi hành từ Đà Nẵng', 'em bé dưới 2 tuổi'], $kq['chua_gom']);

        // Không tin dữ liệu khách gửi: ngày hết phòng, hạng phòng không bán ngày đó, nơi đi lạ
        $gui(['combo_date_id' => $het->id, 'room_type_id' => $w])->assertStatus(422);
        $gui(['combo_date_id' => $tet->id, 'room_type_id' => $this->phong['Ocean Villa with Pool & Slide']])->assertStatus(422);
        $gui(['combo_date_id' => $ngay->id, 'departure_city' => 'Cần Thơ'])->assertStatus(422);
        // Chưa chọn ngày / phòng vẫn gửi được — nhân viên tư vấn sau
        $this->assertNull($gui([])->assertCreated()->json('data.total_usd'));
    }

    public function test_lenh_kiem_tra_vi_sao_khong_thay_muc_maldives(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['email' => 'quan-tri@example.com'])->assignRole('admin');
        User::factory()->create(['email' => 'nv-visa@example.com'])->assignRole('visa');
        // Quyền Maldives bị mất khỏi vai trò admin → lệnh tự thêm lại
        Role::findByName('admin', 'web')->revokePermissionTo('ViewAny:MaldivesCombo');
        $this->assertFalse($admin->fresh()->can('viewAny', MaldivesCombo::class));

        $this->artisan('psv:kiem-tra-maldives')->assertSuccessful()
            ->expectsOutputToContain('Admin đã có các trang Maldives')
            ->expectsOutputToContain('quan-tri@example.com (admin)') // trong dòng "Tài khoản thấy mục Maldives"
            ->expectsOutputToContain('nv-visa@example.com (visa)'); // trong dòng "Tài khoản KHÔNG thấy"
        $this->assertTrue($admin->fresh()->can('viewAny', MaldivesCombo::class));
    }

    public function test_hang_phong_chi_nguoi_lon_va_resort_nua_sao(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->combo->resort->update(['stars' => 4.5]);
        $o = $this->phong['Ocean Villa with Pool & Slide'];
        $this->combo->resort->roomTypes()->whereKey($o)->update(['adults_only' => true]);

        $ct = $this->getJson('/api/v1/maldives/'.$this->combo->slug)->assertOk()->json('data');
        $this->assertSame(4.5, $ct['resort']['stars']);
        $this->assertSame([false, true], array_column($ct['room_types'], 'adults_only'));
        $this->assertSame(4.5, $this->getJson('/api/v1/maldives')->json('data.0.resort.stars'));
        $this->assertSame('4,5 sao', $this->combo->resort->fresh()->saoChu());

        // Hạng phòng chỉ người lớn: không nhận đơn có trẻ em / em bé
        $ngay = $this->combo->dates()->where('start_date', '>=', today())->orderBy('start_date')->first();
        $gui = fn (array $them) => $this->postJson('/api/v1/maldives/bookings', [
            'combo_id' => $this->combo->id, 'combo_date_id' => $ngay->id, 'room_type_id' => $o,
            'customer_name' => 'Nguyễn Văn A', 'customer_phone' => '0901234567', 'adults' => 2, ...$them,
        ]);
        $gui(['children' => 1])->assertStatus(422)->assertJsonPath('message', 'Hạng phòng Ocean Villa with Pool & Slide chỉ nhận khách từ 18 tuổi — vui lòng chọn hạng phòng khác nếu đi cùng trẻ em.');
        $gui(['infants' => 1])->assertStatus(422);
        $gui([])->assertCreated();
    }

    public function test_quan_tri_nhap_combo_va_tao_don(): void
    {
        $this->seed(RoleSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $mk = User::factory()->create()->assignRole('marketing');
        $this->actingAs($mk);

        // Bảng giá: một ô cho mỗi hạng phòng của resort
        Livewire::test(EditMaldivesCombo::class, ['record' => $this->combo->getRouteKey()])->assertOk()
            ->assertSee('Water Villa with Pool &amp; Slide', false)->assertSee('Ocean Villa with Pool &amp; Slide', false)->assertSee('Lịch Tết');
        Livewire::test(CreateMaldivesCombo::class)->assertOk()->assertSee('Chọn resort ở trên');

        // Đổi đường dẫn combo đang bán → link cũ tự chuyển (website chuyển ở proxy)
        $cu = $this->combo->slug;
        $this->combo->update(['slug' => 'sun-siyam-world-bay-sq']);
        $this->assertSame('/maldives/sun-siyam-world-bay-sq', $this->getJson('/api/v1/link-cu/doi-duong-dan')->json('data')['/maldives/'.$cu]);

        // Sale: tạo đơn cho khách gọi điện, để trống giá → tự tính theo bảng giá
        $sale = User::factory()->create()->assignRole('sale');
        $this->actingAs($sale);
        $ngay = $this->combo->dates()->where('start_date', '>=', today())->orderBy('start_date')->first();
        Livewire::test(CreateMaldivesBooking::class)
            ->fillForm(['combo_id' => $this->combo->id, 'combo_date_id' => $ngay->id, 'room_type_id' => $this->phong['Water Villa with Pool & Slide'],
                'departure_city' => 'Hà Nội', 'adults' => 2, 'customer_name' => 'Khách gọi điện', 'customer_phone' => '0909000111', 'status' => 'contacting'])
            ->call('create')->assertHasNoFormErrors();
        $don = MaldivesBooking::firstWhere('customer_name', 'Khách gọi điện');
        $this->assertSame([5262, 'quan_tri', $sale->id, 'Sun Siyam World 5* – bay Singapore Airlines'], [$don->total_usd, $don->source, $don->assigned_to, $don->combo_name]);
        Livewire::test(ListMaldivesBookings::class)->assertOk()->assertSee($don->code);

        // Sale xem được combo nhưng không thêm / sửa
        $this->assertTrue($sale->can('viewAny', MaldivesCombo::class));
        $this->assertFalse($sale->can('create', MaldivesCombo::class));
    }
}
