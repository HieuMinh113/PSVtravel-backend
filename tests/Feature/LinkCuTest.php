<?php

namespace Tests\Feature;

use App\Filament\Resources\Redirects\Pages\CreateRedirect;
use App\Filament\Resources\Redirects\Pages\EditRedirect;
use App\Filament\Resources\Redirects\Pages\ListRedirects;
use App\Filament\Resources\Redirects\RedirectResource;
use App\Models\Destination;
use App\Models\Redirect;
use App\Models\User;
use App\Services\LinkCu;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Guide\Models\Guide;
use Modules\Tour\Models\Tour;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Link của web WordPress cũ (psvtravel.com.vn chuyển sang giữ nguyên đường dẫn)
 * → trang mới: khớp đúng tour / điểm đến / bài viết, không chuyển nhầm khi khớp
 * lơ mơ, không lưu link dò bừa của bot, nhân viên sửa thì máy không ghi đè.
 */
class LinkCuTest extends TestCase
{
    use RefreshDatabase;

    private function tour(string $slug, string $ten, string $loai = 'abroad', array $them = []): Tour
    {
        return Tour::create(['slug' => $slug, 'name' => $ten, 'type' => $loai, 'status' => 'published',
            'adult_price' => 5_000_000, 'duration_days' => 5, 'duration_nights' => 4, ...$them]);
    }

    private function bai(string $slug, string $tieuDe): Guide
    {
        return Guide::create(['title' => $tieuDe, 'slug' => $slug, 'status' => 'published', 'published_at' => now()->subDay()]);
    }

    private function duLieuMau(): void
    {
        $this->tour('bangkok-pattaya-5n4d', 'Tour Thái Lan: Bangkok - Pattaya 5N4Đ');
        $this->tour('trung-quoc-cuu-trai-cau-6n5d', 'Trung Quốc: Thế Giới Cổ Tích Cửu Trại Câu - Hoa Quả Sơn 6N5Đ', 'abroad', ['duration_days' => 6, 'duration_nights' => 5]);
        $this->tour('bac-kinh-thuong-hai', 'Trung Quốc: Bắc Kinh - Thượng Hải - Hàng Châu 6N5Đ', 'abroad', ['duration_days' => 6, 'duration_nights' => 5]);
        $this->tour('mien-tay-2n1d', 'Sài Gòn - Mỹ Tho - Bến Tre - Cần Thơ 2N1Đ', 'domestic', ['duration_days' => 2, 'duration_nights' => 1]);
        $this->tour('phu-quoc-3n2d', 'Phú Quốc 3N2Đ - Cáp treo Hòn Thơm', 'domestic', ['duration_days' => 3, 'duration_nights' => 2]);
        $this->tour('tour-an', 'Đà Lạt ngàn hoa', 'domestic', ['status' => 'draft']);
        Destination::create(['name' => 'Nhật Bản', 'slug' => 'nhat-ban', 'status' => 'published']);
        $this->bai('nhung-pho-tuong-dac-biet-tai-thai-lan', 'Những pho tượng đặc biệt tại Thái Lan');
        $this->bai('cam-nang-du-lich-dai-loan', 'Cẩm nang du lịch Đài Loan từ A đến Z');
        $this->bai('kinh-nghiem-du-lich-nhat-ban', 'Kinh nghiệm du lịch Nhật Bản tự túc');
    }

    public function test_khop_tour_cu_dung_tour_dang_ban_va_khong_chuyen_nham(): void
    {
        $this->duLieuMau();

        $cases = [
            // Trùng đường dẫn
            '/tour_nuoc_ngoai/bangkok-pattaya-5n4d' => [301, '/tour-nuoc-ngoai/bangkok-pattaya-5n4d'],
            '/Tour/Bangkok-Pattaya-5n4d/?utm=x' => [301, '/tour-nuoc-ngoai/bangkok-pattaya-5n4d'],
            // Khác đường dẫn, trùng các từ hiếm (cửu trại câu, hoa quả sơn)
            '/tour_nuoc_ngoai/the-gioi-co-tich-cuu-trai-cau-hoa-qua-son' => [301, '/tour-nuoc-ngoai/trung-quoc-cuu-trai-cau-6n5d'],
            '/tour_nuoc_ngoai/sai-gon-my-tho-ben-tre-can-tho' => [301, '/tour-trong-nuoc/mien-tay-2n1d'],
            '/tour_nuoc_ngoai/song-sanh-dao-ngoc-phu-quoc-3n2d' => [301, '/tour-trong-nuoc/phu-quoc-3n2d'],
            // Chỉ trùng "5N4Đ" / chỉ trùng "Trung Quốc": không đủ chắc → đã gỡ, không chuyển nhầm
            '/tour_nuoc_ngoai/bali-5n4d-new-2023' => [410, null],
            '/product/trung-quoc-truong-gia-gioi' => [410, null], // chung "giới" với Thế Giới Cổ Tích, khác nơi
            // Tour đã ẩn không được dùng
            '/tour/da-lat-ngan-hoa' => [410, null],
        ];
        foreach ($cases as $cu => [$ma, $toi]) {
            $this->assertSame(['ma' => $ma, 'toi' => $toi], LinkCu::tra($cu), $cu);
        }
        $this->assertDatabaseHas('redirects', ['old_path' => '/tour/bangkok-pattaya-5n4d', 'status_code' => 301, 'source' => 'tu_dong', 'hits' => 1]);
        $this->assertDatabaseHas('redirects', ['old_path' => '/tour_nuoc_ngoai/bali-5n4d-new-2023', 'status_code' => 410]);
    }

    public function test_trang_mot_cap_diem_den_noi_va_bai_viet(): void
    {
        $this->duLieuMau();

        $cases = [
            '/nhat-ban' => [301, '/diem-den/nhat-ban'],              // điểm đến có trang riêng
            '/australia' => [301, '/tour-nuoc-ngoai'],
            '/my' => [301, '/tour-nuoc-ngoai'],                      // Mỹ — không dính "Mỹ Tho"
            '/my-tho' => [301, '/tour-trong-nuoc'],                  // nơi chỉ có trong tên tour
            '/da-nang' => [301, '/tour-trong-nuoc'],
            '/cua-hang' => [301, '/tour-nuoc-ngoai'],
            '/ve-bay' => [301, '/ve-may-bay'],
            '/tour_nuoc_ngoai' => [301, '/tour-nuoc-ngoai'],
            '/sitemap_index.xml' => [301, '/sitemap.xml'],
            '/nhung-pho-tuong-dac-biet-tai-thai-lan' => [301, '/cam-nang/nhung-pho-tuong-dac-biet-tai-thai-lan'],
            '/cam-nang-du-lich-dai-loan-tu-a-z' => [301, '/cam-nang/cam-nang-du-lich-dai-loan'],
            '/danh-muc-san-pham/chau-a/nhat-ban' => [301, '/diem-den/nhat-ban'],
            // Bài viết cũ không còn bài tương tự: lưu "chưa khớp" chờ nhân viên
            '/top-5-dia-diem-du-lich-khong-the-bo-qua-tai-viet-nam' => [404, null],
        ];
        foreach ($cases as $cu => [$ma, $toi]) {
            $this->assertSame(['ma' => $ma, 'toi' => $toi], LinkCu::tra($cu), $cu);
        }
        $this->assertDatabaseHas('redirects', ['old_path' => '/top-5-dia-diem-du-lich-khong-the-bo-qua-tai-viet-nam', 'status_code' => 404]);
        $this->assertSame('1', RedirectResource::getNavigationBadge());
    }

    public function test_tep_wordpress_va_link_do_bua_khong_luu(): void
    {
        $cases = [
            '/wp-content/uploads/2020/01/anh.jpg' => [410, null],
            '/chuyen-vien-digital-marketing-02-nhan-vien/feed' => [410, null],
            '/comments/feed' => [410, null],
            '/wp-login.php' => [410, null],
            '/post-sitemap2.xml' => [301, '/sitemap.xml'],
            '/.env' => [404, null],
            '/ads.txt' => [404, null],
            '/admin/config.php' => [404, null],
            '/abc' => [404, null],
            '/../../etc/passwd' => [404, null],
            '/<script>' => [404, null],
        ];
        foreach ($cases as $cu => [$ma, $toi]) {
            $this->assertSame(['ma' => $ma, 'toi' => $toi], LinkCu::tra($cu), $cu);
        }
        $this->assertSame(0, Redirect::count());

        // Bảng đầy link "chưa khớp" (bot dò bừa hàng loạt) thì không lưu thêm
        Redirect::insert(array_map(fn ($i) => ['old_path' => "/bai-cu-so-{$i}", 'status_code' => 404, 'source' => 'tu_dong'],
            range(1, LinkCu::TOI_DA_CHUA_KHOP)));
        LinkCu::tra('/mot-bai-cu-khac-nua');
        $this->assertDatabaseMissing('redirects', ['old_path' => '/mot-bai-cu-khac-nua']);
    }

    public function test_dem_luot_nhan_vien_sua_thi_may_khong_ghi_de_va_api(): void
    {
        $this->duLieuMau();
        LinkCu::tra('/tour/bali-5n4d');
        LinkCu::tra('/tour/bali-5n4d/');
        $r = Redirect::firstWhere('old_path', '/tour/bali-5n4d');
        $this->assertSame([410, 2], [$r->status_code, $r->hits]);

        // Nhân viên chọn trang thay thế → khớp lại không đổi
        $r->forceFill(['status_code' => 301, 'new_path' => '/tour-nuoc-ngoai', 'source' => 'tay'])->save();
        LinkCu::khopLai($r->refresh());
        $this->assertSame('/tour-nuoc-ngoai', $r->refresh()->new_path);

        $this->getJson('/api/v1/link-cu?path='.urlencode('/tour/bali-5n4d'))->assertOk()->assertExactJson(['ma' => 301, 'toi' => '/tour-nuoc-ngoai']);
        $this->getJson('/api/v1/link-cu?path=/nhat-ban')->assertOk()->assertExactJson(['ma' => 301, 'toi' => '/diem-den/nhat-ban']);
        $this->getJson('/api/v1/link-cu')->assertOk()->assertExactJson(['ma' => 404, 'toi' => null]);
        $this->assertSame(3, $r->refresh()->hits);

        // Tên miền API: sitemap chuyển về website
        $this->get('/sitemap.xml')->assertStatus(301)->assertRedirect(rtrim(config('app.frontend_url'), '/').'/sitemap.xml');
        $this->get('/sitemap_index.xml')->assertStatus(301);
    }

    public function test_admin_nhan_vien_dat_tay_va_marketing_xem_duoc(): void
    {
        $this->duLieuMau();
        config(['app.frontend_url' => 'https://psvtravel.com']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $nv = User::factory()->create();
        $nv->assignRole(Role::findOrCreate('staff', 'web'));
        $this->actingAs($nv);

        // Dán cả link → chỉ giữ đường dẫn
        Livewire::test(CreateRedirect::class)
            ->fillForm(['old_path' => 'https://psvtravel.com.vn/Bai-Viet-Cu/', 'status_code' => 301, 'new_path' => 'https://psvtravel.com/cam-nang/kinh-nghiem-du-lich-nhat-ban'])
            ->call('create')->assertHasNoFormErrors();
        $r = Redirect::firstWhere('old_path', '/bai-viet-cu');
        $this->assertSame(['/cam-nang/kinh-nghiem-du-lich-nhat-ban', 'tay'], [$r->new_path, $r->source]);

        Livewire::test(CreateRedirect::class)->fillForm(['old_path' => '/bai-viet-cu', 'status_code' => 410])
            ->call('create')->assertHasFormErrors(['old_path' => 'unique']);
        Livewire::test(CreateRedirect::class)->fillForm(['old_path' => '/vong', 'status_code' => 301, 'new_path' => '/vong'])
            ->call('create')->assertHasFormErrors(['new_path']);
        Livewire::test(CreateRedirect::class)->fillForm(['old_path' => '/ra-ngoai', 'status_code' => 301, 'new_path' => 'https://vi-du.com/x'])
            ->call('create')->assertHasFormErrors(['new_path']);
        Livewire::test(CreateRedirect::class)->fillForm(['old_path' => '/co dau cach', 'status_code' => 410])
            ->call('create')->assertHasFormErrors(['old_path']);

        // Dòng máy khớp → nhân viên sửa thành 410: bỏ trang đích, thành "Nhân viên"
        LinkCu::tra('/tour/bangkok-pattaya-5n4d');
        $may = Redirect::firstWhere('old_path', '/tour/bangkok-pattaya-5n4d');
        Livewire::test(EditRedirect::class, ['record' => $may->getRouteKey()])
            ->fillForm(['status_code' => 410])->call('save')->assertHasNoFormErrors();
        $this->assertSame([410, null, 'tay'], [$may->refresh()->status_code, $may->new_path, $may->source]);

        $mk = User::factory()->create();
        $mk->assignRole(Role::findOrCreate('marketing', 'web'));
        $this->actingAs($mk);
        Livewire::test(ListRedirects::class)->assertOk()->assertSee('/bai-viet-cu');

        $this->actingAs(User::factory()->create());
        $this->assertFalse(auth()->user()->can('viewAny', Redirect::class));
    }

    public function test_lenh_nhap_danh_sach_tu_nhat_ky(): void
    {
        $this->duLieuMau();
        $tep = tempnam(sys_get_temp_dir(), 'link');
        file_put_contents($tep, implode("\n", [
            '     34 /tour_nuoc_ngoai/sai-gon-my-tho-ben-tre-can-tho',
            '     68 /australia',
            '     12 /wp-content/uploads/2021/05/a.jpg',
            '      7 /top-5-dia-diem-du-lich-khong-the-bo-qua-tai-viet-nam',
            '/Australia/',
            'dong rac khong hop le @@',
        ]));

        $this->artisan('psv:link-cu', ['tep' => $tep, '--xem' => true])->assertSuccessful()->expectsOutputToContain('CHƯA lưu');
        $this->assertSame(0, Redirect::count());

        $this->artisan('psv:link-cu', ['tep' => $tep])->assertSuccessful()
            ->expectsOutputToContain('Chuyển sang trang mới: 2 link (103 lượt)');
        $this->assertDatabaseHas('redirects', ['old_path' => '/australia', 'hits' => 69, 'new_path' => '/tour-nuoc-ngoai']);
        $this->assertDatabaseHas('redirects', ['old_path' => '/top-5-dia-diem-du-lich-khong-the-bo-qua-tai-viet-nam', 'status_code' => 404, 'hits' => 7]);
        $this->assertSame(3, Redirect::count()); // ảnh wp-content không lưu

        // Chạy lại không đếm đôi
        $this->artisan('psv:link-cu', ['tep' => $tep])->assertSuccessful();
        $this->assertSame(69, Redirect::firstWhere('old_path', '/australia')->hits);
        @unlink($tep);
    }
}
