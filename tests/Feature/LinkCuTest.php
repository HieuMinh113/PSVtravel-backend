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
use Modules\Category\Models\Category;
use Modules\Guide\Models\Guide;
use Modules\Tour\Models\Tour;
use Modules\Visa\Models\VisaCountry;
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

    /** Các trường hợp lấy từ lần chạy thử trên máy chủ thật (10/2026) */
    public function test_khong_khop_nham_noi_khac_va_ve_danh_muc_dung_noi(): void
    {
        $nhat = $this->tour('cung-duong-vang-nhat-ban', 'Cung đường vàng Nhật Bản: Osaka - Phú Sĩ - Nagoya - Kyoto', 'abroad', ['duration_days' => 6, 'duration_nights' => 5]);
        $tq = $this->tour('thanhdo-cuutraicau', 'Thành Đô - Cửu Trại Câu - Gia Cơ Sơn', 'abroad', ['duration_days' => 6, 'duration_nights' => 5]);
        $chauAu = $this->tour('phap-thuy-sy-y-vatican-10n9d', 'Pháp - Thụy Sỹ - Ý - Vatican 10N9Đ', 'abroad', ['duration_days' => 10, 'duration_nights' => 9]);
        $bac = $this->tour('tphcm-ha-noi-ha-long-sapa', 'TP.HCM - Hà Nội - Hạ Long - Ninh Bình - Sapa', 'domestic');
        $this->bai('kinh-nghiem-du-lich-nhat-ban-6-ngay-5-dem', 'Cẩm nang du lịch Nhật Bản 6 ngày 5 đêm từ A-Z');
        $this->bai('di-du-lich-trung-quoc-mang-gi', 'Đi du lịch Trung Quốc nên chuẩn bị gì, mang gì?');
        $this->bai('kinh-nghiem-du-lich-cuu-trai-cau-thanh-do', 'Kinh nghiệm du lịch Cửu Trại Câu Thành Đô – thiên đường hạ giới');
        $danhMuc = fn (string $slug, string $loai, array $tours) => Category::create(['type' => $loai, 'name' => $slug, 'slug' => $slug, 'status' => 'published'])
            ->tours()->attach(array_map(fn ($t) => $t->id, $tours));
        $danhMuc('dong-bac-a', 'abroad', [$nhat]);
        $danhMuc('trung-quoc', 'abroad', [$tq]);
        $danhMuc('mien-nam', 'domestic', [$bac]);
        $danhMuc('chau-au', 'abroad', []); // không có tour → không chuyển vào danh mục rỗng
        foreach (['dai-loan' => 'published', 'han-quoc' => 'published', 'my' => 'published', 'uc' => 'hidden'] as $slug => $tt) {
            VisaCountry::create(['name' => $slug, 'slug' => $slug, 'status' => $tt, 'price' => 1_000_000]);
        }

        $cases = [
            // Trước đây khớp nhầm: cùng "cẩm nang… từ A–Z" / "chuẩn bị gì" / "thiên đường hạ giới" nhưng KHÁC NƠI
            '/cam-nang-du-lich-dai-loan-tu-a-z' => [404, null],
            '/ban-can-chuan-bi-gi-khi-di-du-lich-han-quoc' => [404, null],
            '/kham-pha-thien-duong-ha-gioi-truong-gia-gioi' => [301, '/tour-nuoc-ngoai?category=trung-quoc'],
            // Vẫn khớp đúng
            '/tour_nuoc_ngoai/chau-au-4-nuoc-10n9d-phap-thuy-si-y-vatican' => [301, '/tour-nuoc-ngoai/phap-thuy-sy-y-vatican-10n9d'],
            '/tour_nuoc_ngoai/ha-noi-vinh-ha-long-4-ngay-3-dem' => [301, '/tour-trong-nuoc/tphcm-ha-noi-ha-long-sapa'],
            '/tour/du-lich-trung-quoc-6n5d-thanh-do-cuu-trai-cau-lac-son' => [301, '/tour-nuoc-ngoai/thanhdo-cuutraicau'],
            // Tour cũ không còn tour tương tự → danh mục đúng nước / vùng; danh mục rỗng thì 410
            '/tour_nuoc_ngoai/nhat-ban-nagoya-toyohashi-tokyo-narita-6n5d-tet-2023' => [301, '/tour-nuoc-ngoai?category=dong-bac-a'],
            '/tour_nuoc_ngoai/sai-gon-my-tho-ben-tre-can-tho-cho-noi-con-son-can-nha-mau-tim' => [301, '/tour-trong-nuoc?category=mien-nam'],
            '/tour_nuoc_ngoai/tour-bac-au-phan-lan-thuy-dien-nauy-dan-mach-2023' => [410, null],
            // Nơi ví von / lạc vùng không phải nơi của tour
            '/tour_nuoc_ngoai/nam-du-2n2d-vinh-ha-long-o-phuong-nam' => [301, '/tour-trong-nuoc?category=mien-nam'],
            '/tour_nuoc_ngoai/phu-quy-2n2d-bali-cua-viet-nam' => [301, '/tour-trong-nuoc?category=mien-nam'],
            '/tour_nuoc_ngoai/du-lich-han-quoc-mua-thu-seoul-everland-dao-nami-tu-ha-noi-gia-tot' => [301, '/tour-nuoc-ngoai?category=dong-bac-a'],
            '/nhat-ban' => [301, '/tour-nuoc-ngoai?category=dong-bac-a'],
            '/mien-tay' => [301, '/tour-trong-nuoc?category=mien-nam'],
            '/chau-au' => [301, '/tour-nuoc-ngoai'],
            // Chủ đề
            '/visa-du-lich-dai-loan' => [301, '/lam-visa/dai-loan'],
            '/thu-tuc-tham-than-nhan-dai-loan' => [301, '/lam-visa/dai-loan'],
            '/visa-chau-my' => [301, '/lam-visa/my'],
            '/visa-du-lich-uc' => [301, '/lam-visa'],               // trang visa Úc đang ẩn
            '/huong-dan-chi-tiet-thu-tuc-xin-visa-chau-au' => [301, '/lam-visa'],
            '/truong-phong-visa-ho-chieu-02-nhan-vien' => [301, '/tuyen-dung'],
            '/tuyen-dung-nhan-su-2022' => [301, '/tuyen-dung'],
            '/chuyen-cung-cap-ve-may-bay-doan-booking-seri-ve-le-trong-va-ngoai-nuoc' => [301, '/ve-may-bay'],
            '/dich-vu-to-chuc-teambuiliding-cho-cong-ty-va-doanh-nghiep' => [301, '/team-building'],
            '/quoc-gia' => [301, '/tour-nuoc-ngoai'],
            '/tinh-thanh-pho' => [301, '/tour-trong-nuoc'],
            '/chinh-sach-bao-mat-thong-tin' => [301, '/chinh-sach-bao-mat'],
            '/apple-app-site-association' => [404, null],
        ];
        foreach ($cases as $cu => [$ma, $toi]) {
            $this->assertSame(['ma' => $ma, 'toi' => $toi], LinkCu::tra($cu), $cu);
        }
        $this->assertDatabaseMissing('redirects', ['old_path' => '/apple-app-site-association']);

        // Dòng máy đã lưu bằng bộ khớp cũ → lệnh khớp lại sửa, dòng không phải trang thì bỏ
        Redirect::query()->where('old_path', '/ban-can-chuan-bi-gi-khi-di-du-lich-han-quoc')
            ->update(['status_code' => 301, 'new_path' => '/cam-nang/di-du-lich-trung-quoc-mang-gi']);
        Redirect::create(['old_path' => '/apple-app-site-association', 'status_code' => 404]);
        Redirect::create(['old_path' => '/visa-chau-a', 'status_code' => 404, 'source' => 'tay']);
        $this->artisan('psv:link-cu', ['--khop-lai' => true])->assertSuccessful()->expectsOutputToContain('2 link đổi trang đích');
        $this->assertDatabaseHas('redirects', ['old_path' => '/ban-can-chuan-bi-gi-khi-di-du-lich-han-quoc', 'status_code' => 404, 'new_path' => null]);
        $this->assertDatabaseMissing('redirects', ['old_path' => '/apple-app-site-association']);
        $this->assertDatabaseHas('redirects', ['old_path' => '/visa-chau-a', 'status_code' => 404]); // nhân viên đặt: giữ nguyên
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
