<?php

namespace Tests\Feature;

use App\Filament\Resources\GuideCities\Pages\EditGuideCity;
use App\Filament\Resources\GuideCities\RelationManagers\SpotsRelationManager;
use App\Filament\Resources\GuideCountries\Pages\CreateGuideCountry;
use App\Filament\Resources\GuideCountries\Pages\EditGuideCountry;
use App\Filament\Resources\GuideCountries\Pages\ListGuideCountries;
use App\Models\User;
use App\Services\LinkGoogleMaps;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as YeuCau;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Modules\Guide\Database\Seeders\BanDoCamNangMauSeeder;
use Modules\Guide\Database\Seeders\QuyenBanDoCamNangSeeder;
use Modules\Guide\Models\Guide;
use Modules\Guide\Models\GuideCity;
use Modules\Guide\Models\GuideCountry;
use Modules\Guide\Models\GuideSpot;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Bản đồ cẩm nang: quả địa cầu → nước → thành phố → điểm → bài cẩm nang.
 */
class BanDoCamNangTest extends TestCase
{
    use RefreshDatabase;

    private function bai(string $slug, string $trangThai = 'published'): Guide
    {
        return Guide::create(['title' => 'Bài '.$slug, 'slug' => $slug, 'status' => $trangThai, 'published_at' => now()->subDay(), 'excerpt' => 'Tóm tắt '.$slug]);
    }

    public function test_ranh_gioi_nuoc_co_ten_tieng_viet_va_nuoc_nho(): void
    {
        $this->assertSame('Trung Quốc', GuideCountry::duLieuNuoc('CHN')['ten']);
        $this->assertSame('Singapore', GuideCountry::duLieuNuoc('sgp')['ten']);
        $this->assertSame('Hồng Kông', GuideCountry::duLieuNuoc('HKG')['ten']);
        $this->assertSame('MultiPolygon', GuideCountry::duLieuNuoc('VNM')['hinh']['type']);
        $this->assertNull(GuideCountry::duLieuNuoc('XXX'));
        $this->assertArrayHasKey('JPN', GuideCountry::luaChonNuoc());
    }

    public function test_api_tra_nuoc_thanh_pho_diem_va_bai_viet_dang_hien(): void
    {
        $tq = GuideCountry::create(['code' => 'CHN', 'name' => 'Trung Quốc', 'slug' => 'trung-quoc']);
        GuideCountry::create(['code' => 'JPN', 'name' => 'Nhật Bản', 'slug' => 'nhat-ban']); // chưa có thành phố → không hiện
        GuideCountry::create(['code' => 'KOR', 'name' => 'Hàn Quốc', 'slug' => 'han-quoc', 'status' => 'hidden']);
        $sh = GuideCity::create(['guide_country_id' => $tq->id, 'name' => 'Thượng Hải', 'slug' => 'thuong-hai', 'lat' => 31.2304, 'lng' => 121.4737,
            'guide_id' => $this->bai('thuong-hai-tu-tuc')->id]);
        GuideCity::create(['guide_country_id' => $tq->id, 'name' => 'Bắc Kinh', 'slug' => 'bac-kinh', 'lat' => 39.9, 'lng' => 116.4, 'status' => 'hidden']);
        GuideSpot::create(['guide_city_id' => $sh->id, 'name' => 'Bến Thượng Hải', 'type' => 'tham_quan', 'lat' => 31.24, 'lng' => 121.49,
            'guide_id' => $this->bai('ben-thuong-hai')->id]);
        GuideSpot::create(['guide_city_id' => $sh->id, 'name' => 'Quán nháp', 'type' => 'an_uong', 'lat' => 31.22, 'lng' => 121.48,
            'guide_id' => $this->bai('bai-nhap', 'draft')->id]);
        GuideSpot::create(['guide_city_id' => $sh->id, 'name' => 'Điểm ẩn', 'lat' => 31.2, 'lng' => 121.4, 'status' => 'hidden']);

        $r = $this->getJson('/api/v1/guide-map')->assertOk();

        $this->assertSame(['trung-quoc'], array_column($r->json('data.countries'), 'slug'));
        $nuoc = $r->json('data.countries.0');
        $this->assertSame('MultiPolygon', $nuoc['geometry']['type']);
        $this->assertCount(4, $nuoc['bbox']);
        $this->assertSame(['thuong-hai'], array_column($nuoc['cities'], 'slug'));
        $tp = $nuoc['cities'][0];
        $this->assertSame('thuong-hai-tu-tuc', $tp['guide']['slug']);
        $this->assertSame(['Bến Thượng Hải', 'Quán nháp'], array_column($tp['spots'], 'name'));
        $this->assertSame('ben-thuong-hai', $tp['spots'][0]['guide']['slug']);
        $this->assertNull($tp['spots'][1]['guide']); // bài nháp: điểm vẫn hiện, không có nút đọc
        $this->assertSame('Ăn uống', $r->json('data.types.an_uong'));

        // Trang bài biết bài nằm ở đâu trên bản đồ (để mở bản đồ đúng thành phố)
        $bai = $this->getJson('/api/v1/guides/ben-thuong-hai')->assertOk();
        $this->assertSame([['id' => GuideSpot::where('name', 'Bến Thượng Hải')->value('id'), 'name' => 'Bến Thượng Hải', 'lat' => 31.24, 'lng' => 121.49,
            'tp' => 'thuong-hai', 'ten_tp' => 'Thượng Hải', 'nuoc' => 'trung-quoc']], $bai->json('data.map_spots'));
        $this->assertSame([], $bai->json('data.map_cities'));
        $tongQuan = $this->getJson('/api/v1/guides/thuong-hai-tu-tuc')->assertOk();
        $this->assertSame([['tp' => 'thuong-hai', 'ten_tp' => 'Thượng Hải', 'nuoc' => 'trung-quoc']], $tongQuan->json('data.map_cities'));
    }

    public function test_du_lieu_mau_va_lenh_don(): void
    {
        $this->seed(BanDoCamNangMauSeeder::class);
        $this->assertGreaterThanOrEqual(5, GuideCountry::count());
        $this->assertTrue(GuideSpot::where('name', 'Bến Thượng Hải (The Bund)')->whereNotNull('guide_id')->exists());
        $this->assertSame(0, GuideSpot::whereNull('guide_id')->count()); // điểm nào cũng mở ra một bài
        $this->getJson('/api/v1/guide-map')->assertOk()->assertJsonCount(GuideCountry::count(), 'data.countries');

        $this->artisan('psv:don-du-lieu-mau', ['--force' => true])->assertSuccessful();
        $this->assertSame(0, GuideCountry::count() + GuideCity::count() + GuideSpot::count());
    }

    public function test_migrate_tu_cap_quyen_ban_do_cho_admin_va_nguoi_sua_cam_nang(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $sieu = Role::findOrCreate('super_admin', 'web');
        $bienTap = Role::findOrCreate('bien_tap', 'web');
        $bienTap->givePermissionTo(Permission::findOrCreate('Update:Guide', 'web'));
        $sale = Role::findOrCreate('sale', 'web');

        (new QuyenBanDoCamNangSeeder)->run();

        $this->assertTrue($sieu->fresh()->hasPermissionTo('ViewAny:GuideCountry'));
        $this->assertTrue($sieu->fresh()->hasPermissionTo('Reorder:GuideCity'));
        $this->assertTrue($bienTap->fresh()->hasPermissionTo('Create:GuideSpot'));
        $this->assertFalse($sale->fresh()->hasPermissionTo('ViewAny:GuideCountry'));

        // Người sửa được cẩm nang đăng nhập là thấy ngay hai mục trên menu
        // (super_admin bị bắt bật 2FA trước khi vào nên không dùng để thử ở đây)
        $this->actingAs(User::factory()->create()->assignRole('bien_tap'));
        $this->get('/admin')->assertOk()->assertSee('Bản đồ: Quốc gia')->assertSee('Bản đồ: Thành phố &amp; điểm', false);
        $this->get('/admin/ban-do-quoc-gia/create')->assertOk();
    }

    public function test_quan_tri_them_nuoc_thanh_pho_diem(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = User::factory()->create();
        foreach (['GuideCountry', 'GuideCity', 'GuideSpot'] as $m) {
            foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete'] as $q) {
                $admin->givePermissionTo(Permission::findOrCreate("{$q}:{$m}", 'web'));
            }
        }
        $admin->givePermissionTo(Permission::findOrCreate('ViewAny:Guide', 'web'));
        $this->actingAs($admin);

        $this->get('/admin/ban-do-quoc-gia')->assertOk();
        $this->get('/admin/ban-do-thanh-pho/create')->assertOk()->assertSee('Vị trí trên bản đồ');

        Livewire::test(CreateGuideCountry::class)
            ->fillForm(['code' => 'CHN'])
            ->assertSchemaStateSet(['name' => 'Trung Quốc', 'slug' => 'trung-quoc'])
            ->call('create')
            ->assertHasNoFormErrors();
        $tq = GuideCountry::sole();

        $sh = GuideCity::create(['guide_country_id' => $tq->id, 'name' => 'Thượng Hải', 'slug' => 'thuong-hai', 'lat' => 31.23, 'lng' => 121.47]);
        $this->get('/admin/ban-do-thanh-pho/'.$sh->id.'/edit')->assertOk()->assertSee('chon-vi-tri.js');

        $bai = $this->bai('ben-thuong-hai');
        Livewire::test(SpotsRelationManager::class, ['ownerRecord' => $sh, 'pageClass' => EditGuideCity::class])
            ->callAction(TestAction::make('create')->table(), data: [
                'name' => 'Bến Thượng Hải', 'type' => 'tham_quan', 'guide_id' => $bai->id, 'lat' => 31.24, 'lng' => 121.49, 'status' => 'published',
            ])
            ->assertHasNoFormErrors();
        $this->assertSame($bai->id, $sh->spots()->sole()->guide_id);

        // Toạ độ ngoài phạm vi bị chặn
        Livewire::test(SpotsRelationManager::class, ['ownerRecord' => $sh, 'pageClass' => EditGuideCity::class])
            ->callAction(TestAction::make('create')->table(), data: ['name' => 'Sai', 'type' => 'khac', 'lat' => 120, 'lng' => 500, 'status' => 'published'])
            ->assertHasFormErrors(['lat', 'lng', 'guide_id']); // toạ độ ngoài phạm vi, chưa gắn bài

        // Không có quyền thì không vào được
        $this->actingAs(User::factory()->create());
        $this->get('/admin/ban-do-quoc-gia')->assertForbidden();
    }

    private function nguoiSuaBanDo(): User
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $u = User::factory()->create();
        $u->givePermissionTo(Permission::findOrCreate('Update:GuideSpot', 'web'));

        return $u;
    }

    public function test_dan_link_rut_gon_google_maps_tu_ra_toa_do(): void
    {
        // Link chia sẻ trên điện thoại: maps.app.goo.gl → maps.google.com/?q=Tên&ftid=… (KHÔNG có toạ độ)
        // → www.google.com/maps?… → trang có ảnh xem trước ghi toạ độ địa điểm
        Http::fake([
            'maps.app.goo.gl/*' => Http::response('', 302, ['Location' => 'https://maps.google.com/?q=C%E1%BA%A7u+R%E1%BB%93ng,+%C4%90%C3%A0+N%E1%BA%B5ng&ftid=0x314219:0x7a2&entry=gps']),
            'maps.google.com/*' => Http::response('', 302, ['Location' => 'https://www.google.com/maps?q=C%E1%BA%A7u+R%E1%BB%93ng&ftid=0x314219:0x7a2']),
            'www.google.com/*' => Http::response('<html><head><meta content="https://maps.google.com/maps/api/staticmap?center=16.0612%2C108.2269&amp;zoom=17&amp;size=900x900&amp;markers=16.0610759%2C108.2272185&amp;sensor=false" property="og:image"></head></html>'),
        ]);
        $this->actingAs($this->nguoiSuaBanDo());

        $this->getJson('/admin/psv/doc-link-ban-do?url='.urlencode('https://maps.app.goo.gl/noyF5TGzXSwqPLf49'))
            ->assertOk()
            ->assertExactJson(['lat' => 16.0610759, 'lng' => 108.2272185, 'zoom' => null, 'ten' => 'Cầu Rồng']);
        Http::assertSentCount(3);
        Http::assertSent(fn (YeuCau $r) => $r->url() === 'https://www.google.com/maps?q=C%E1%BA%A7u+R%E1%BB%93ng&ftid=0x314219:0x7a2');
    }

    public function test_link_rut_gon_chuyen_toi_link_day_du_thi_doc_ngay(): void
    {
        Http::fake([
            'maps.app.goo.gl/*' => Http::response('', 302, ['Location' => 'https://www.google.com/maps/place/B%C3%A0+N%C3%A0+Hills/@15.99,107.99,15z/data=!3m1!4b1!4m6!3m5!1s0x0:0x0!8m2!3d15.9951!4d107.9966!16s?entry=ttu']),
        ]);
        $this->actingAs($this->nguoiSuaBanDo());

        $this->getJson('/admin/psv/doc-link-ban-do?url='.urlencode('https://maps.app.goo.gl/abc'))
            ->assertOk()
            ->assertJson(['lat' => 15.9951, 'lng' => 107.9966, 'ten' => 'Bà Nà Hills']);
        Http::assertSentCount(1); // không cần tải trang Google
    }

    public function test_doc_link_chi_di_toi_ten_mien_google_va_can_quyen(): void
    {
        Http::fake([
            'maps.app.goo.gl/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data']),
            '*' => Http::response('16.1, 108.1'),
        ]);
        $this->actingAs($this->nguoiSuaBanDo());

        // Link không phải Google: từ chối, không gửi đi đâu
        foreach (['http://maps.app.goo.gl/x', 'https://evil.example/maps/@1,2', 'https://google.com.evil.example/maps', 'https://user@www.google.com/maps', 'https://goo.gl/abc', 'https://www.google.com:8443/maps'] as $url) {
            $this->getJson('/admin/psv/doc-link-ban-do?url='.urlencode($url))->assertStatus(422);
        }
        Http::assertNothingSent();

        // Chuyển hướng ra ngoài Google: dừng lại, không đi theo
        $this->getJson('/admin/psv/doc-link-ban-do?url='.urlencode('https://maps.app.goo.gl/x'))->assertStatus(422)->assertJsonStructure(['message']);
        Http::assertSentCount(1);
        Http::assertNotSent(fn (YeuCau $r) => str_contains($r->url(), '169.254'));

        // Không có quyền sửa bản đồ thì không dùng được
        $this->actingAs(User::factory()->create());
        $this->getJson('/admin/psv/doc-link-ban-do?url='.urlencode('https://maps.app.goo.gl/x'))->assertForbidden();
    }

    public function test_doc_toa_do_trong_link_va_trang_google_maps(): void
    {
        $this->assertSame(['lat' => 16.061, 'lng' => 108.2272, 'zoom' => null],
            LinkGoogleMaps::toaDoTrongLink('https://www.google.com/maps/place/X/@16.06,108.22,17z/data=!4m6!3m5!8m2!3d16.0610!4d108.2272'));
        $this->assertSame(['lat' => 16.0544, 'lng' => 108.2022, 'zoom' => 17.0],
            LinkGoogleMaps::toaDoTrongLink('https://www.google.com/maps/@16.0544,108.2022,17z'));
        $this->assertSame(16.0544, LinkGoogleMaps::toaDoTrongLink('https://maps.google.com/?q=16.0544%2C108.2022')['lat']);
        $this->assertSame(108.2022, LinkGoogleMaps::toaDoTrongLink('https://www.google.com/maps/search/16.0544,+108.2022?entry=tts')['lng']);
        $this->assertNull(LinkGoogleMaps::toaDoTrongLink('https://maps.google.com/?q=C%E1%BA%A7u+R%E1%BB%93ng&ftid=0x1:0x2'));
        $this->assertNull(LinkGoogleMaps::toaDoTrongLink('https://www.google.com/maps/@95,200,3z'));

        // Trang không có ảnh xem trước: lấy tâm trong APP_INITIALIZATION_STATE (kinh độ đứng trước)
        $this->assertSame(['lat' => 31.2397, 'lng' => 121.4998, 'zoom' => null],
            LinkGoogleMaps::toaDoTrongTrang('<script>window.APP_INITIALIZATION_STATE=[[[1500.2,121.4998,31.2397],[0,0,0],[1024,768],13.1]]</script>'));
        $this->assertNull(LinkGoogleMaps::toaDoTrongTrang('<html>không có gì</html>'));
    }

    public function test_chon_mau_to_nuoc_tren_ban_do(): void
    {
        $tq = GuideCountry::create(['code' => 'CHN', 'name' => 'Trung Quốc', 'slug' => 'trung-quoc']);
        $nb = GuideCountry::create(['code' => 'JPN', 'name' => 'Nhật Bản', 'slug' => 'nhat-ban', 'color' => '#f43f5e']);
        $vn = GuideCountry::create(['code' => 'VNM', 'name' => 'Việt Nam', 'slug' => 'viet-nam', 'color' => 'đỏ']); // dữ liệu hỏng → mặc định
        foreach ([$tq, $nb, $vn] as $i => $n) {
            GuideCity::create(['guide_country_id' => $n->id, 'name' => 'TP '.$i, 'slug' => 'tp-'.$i, 'lat' => 10 + $i, 'lng' => 100 + $i]);
        }

        $mau = collect($this->getJson('/api/v1/guide-map')->assertOk()->json('data.countries'))->pluck('color', 'slug');
        $this->assertSame('#76DA51', $mau['trung-quoc']); // chưa chọn → xanh lá mặc định
        $this->assertSame('#F43F5E', $mau['nhat-ban']);
        $this->assertSame('#76DA51', $mau['viet-nam']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $nv = User::factory()->create();
        foreach (['ViewAny', 'View', 'Update'] as $q) {
            $nv->givePermissionTo(Permission::findOrCreate("{$q}:GuideCountry", 'web'));
        }
        $this->actingAs($nv);

        Livewire::test(EditGuideCountry::class, ['record' => $tq->getRouteKey()])
            ->assertFormFieldExists('color')
            ->fillForm(['color' => 'cam'])
            ->call('save')
            ->assertHasFormErrors(['color' => 'regex']);
        Livewire::test(EditGuideCountry::class, ['record' => $tq->getRouteKey()])
            ->fillForm(['color' => '#EA580C'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('#EA580C', $tq->fresh()->color);

        // Chọn nhiều nước → đổi cùng một màu
        Livewire::test(ListGuideCountries::class)
            ->selectTableRecords([$tq, $nb])
            ->callAction(TestAction::make('doiMau')->table()->bulk(), data: ['color' => '#2563eb'])
            ->assertHasNoFormErrors();
        $this->assertSame('#2563EB', $tq->fresh()->color);
        $this->assertSame('#2563EB', $nb->fresh()->color);
        $this->assertSame('đỏ', $vn->fresh()->color); // nước không chọn giữ nguyên

        // Không có quyền sửa thì không thấy nút đổi màu
        $this->actingAs(User::factory()->create()->givePermissionTo(Permission::findOrCreate('ViewAny:GuideCountry', 'web')));
        Livewire::test(ListGuideCountries::class)->assertActionHidden(TestAction::make('doiMau')->table()->bulk());
    }
}
