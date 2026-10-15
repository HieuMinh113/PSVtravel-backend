<?php

namespace Tests\Feature;

use App\Filament\Resources\Tours\Pages\CreateTour;
use App\Filament\Resources\Tours\Pages\EditTour;
use App\Filament\Resources\Tours\Pages\ListTours;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Category\Models\Category;
use Modules\Tour\Models\Tour;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Trang quản trị tour:
 *  - bấm "Lưu thay đổi" thì Ở LẠI trang sửa (trước đây bị đưa về danh sách)
 *  - tạo tour mới xong mở luôn trang sửa tour đó để thêm lịch khởi hành, lịch trình, ảnh
 *  - danh sách tour có cột "Danh mục"
 */
class TrangSuaTourTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $nv = User::factory()->create();
        foreach (['ViewAny', 'View', 'Create', 'Update'] as $q) {
            $nv->givePermissionTo(Permission::findOrCreate("{$q}:Tour", 'web'));
        }
        $this->actingAs($nv);
    }

    public function test_luu_thay_doi_o_lai_trang_sua(): void
    {
        $tour = Tour::create(['slug' => 'nha-trang', 'name' => 'Nha Trang', 'type' => 'domestic', 'status' => 'published', 'adult_price' => 2_986_000]);

        Livewire::test(EditTour::class, ['record' => $tour->getRouteKey()])
            ->fillForm(['name' => 'Hành trình duyên hải Vĩnh Hy – Nha Trang'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNoRedirect();

        $this->assertSame('Hành trình duyên hải Vĩnh Hy – Nha Trang', $tour->fresh()->name);
    }

    public function test_tao_tour_xong_mo_trang_sua_tour_vua_tao(): void
    {
        Livewire::test(CreateTour::class)
            ->fillForm(['name' => 'Mũi Né – Miền cát trắng', 'slug' => 'mui-ne', 'type' => 'domestic', 'duration_days' => 2, 'duration_nights' => 1])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(EditTour::getUrl(['record' => Tour::where('slug', 'mui-ne')->firstOrFail()]));
    }

    public function test_danh_sach_tour_co_cot_danh_muc(): void
    {
        $mt = Category::create(['type' => 'domestic', 'name' => 'Miền Trung', 'slug' => 'mien-trung', 'status' => 'published']);
        $mn = Category::create(['type' => 'domestic', 'name' => 'Miền Nam', 'slug' => 'mien-nam', 'status' => 'published']);
        $co = Tour::create(['slug' => 'nha-trang', 'name' => 'Nha Trang', 'type' => 'domestic', 'status' => 'published', 'adult_price' => 1]);
        $co->categories()->attach([$mt->id, $mn->id]);
        $khong = Tour::create(['slug' => 'cat-tien', 'name' => 'Cát Tiên', 'type' => 'domestic', 'status' => 'published', 'adult_price' => 1]);

        Livewire::test(ListTours::class)
            ->assertTableColumnExists('categories.name')
            ->assertSee(['Miền Trung', 'Miền Nam', 'Chưa có'])
            ->searchTable('Miền Trung')
            ->assertCanSeeTableRecords([$co])
            ->assertCanNotSeeTableRecords([$khong]);
    }
}
