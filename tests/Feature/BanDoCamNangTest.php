<?php

namespace Tests\Feature;

use App\Filament\Resources\GuideCities\Pages\EditGuideCity;
use App\Filament\Resources\GuideCities\RelationManagers\SpotsRelationManager;
use App\Filament\Resources\GuideCountries\Pages\CreateGuideCountry;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Guide\Database\Seeders\BanDoCamNangMauSeeder;
use Modules\Guide\Models\Guide;
use Modules\Guide\Models\GuideCity;
use Modules\Guide\Models\GuideCountry;
use Modules\Guide\Models\GuideSpot;
use Spatie\Permission\Models\Permission;
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
}
