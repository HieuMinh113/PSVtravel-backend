<?php

namespace Tests\Feature;

use App\Filament\Resources\Tours\Pages\CreateTour;
use App\Filament\Resources\Tours\Pages\EditTour;
use App\Filament\Resources\Tours\Pages\ListTours;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    /** Thẻ tour ngoài web khung 16:9: ảnh bìa mới phải đúng 16:9; ảnh cũ lệch tỉ lệ vẫn lưu được */
    public function test_anh_bia_tai_moi_phai_dung_ti_le_16_9(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('tours/cu-4-3.jpg', UploadedFile::fake()->image('cu.jpg', 1200, 900)->getContent());
        $tour = Tour::create(['slug' => 'seoul', 'name' => 'Seoul Nami', 'type' => 'abroad', 'status' => 'published',
            'adult_price' => 17_990_000, 'cover_image' => 'tours/cu-4-3.jpg']);
        $trang = fn () => Livewire::test(EditTour::class, ['record' => $tour->getRouteKey()]);

        // Ảnh cũ 4:3 có sẵn: sửa chỗ khác vẫn lưu bình thường
        $trang()->fillForm(['name' => 'Seoul - Nami - Lotte'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('tours/cu-4-3.jpg', $tour->fresh()->cover_image);

        // Tải ảnh mới 4:3 → báo phải cắt lại 16:9
        $trang()->fillForm(['cover_image' => [UploadedFile::fake()->image('bia.jpg', 1200, 900)]])->call('save')
            ->assertHasFormErrors(['cover_image']);

        // Ảnh 16:9 → lưu được
        // (mảng = thay hẳn ảnh cũ, như người dùng xoá ảnh cũ rồi tải ảnh mới)
        $trang()->fillForm(['cover_image' => [UploadedFile::fake()->image('bia.jpg', 1600, 900)]])->call('save')->assertHasNoFormErrors();
        $this->assertNotSame('tours/cu-4-3.jpg', $tour->fresh()->cover_image);
        Storage::disk('public')->assertExists($tour->fresh()->cover_image);
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

    public function test_loc_danh_sach_tour_theo_danh_muc(): void
    {
        $mt = Category::create(['type' => 'domestic', 'name' => 'Miền Trung', 'slug' => 'mien-trung', 'status' => 'published']);
        $tl = Category::create(['type' => 'abroad', 'name' => 'Thái Lan', 'slug' => 'thai-lan', 'status' => 'published']);
        $nhaTrang = Tour::create(['slug' => 'nha-trang', 'name' => 'Nha Trang', 'type' => 'domestic', 'status' => 'published', 'adult_price' => 1]);
        $nhaTrang->categories()->attach($mt->id);
        $bangkok = Tour::create(['slug' => 'bangkok', 'name' => 'Bangkok', 'type' => 'abroad', 'status' => 'published', 'adult_price' => 1]);
        $bangkok->categories()->attach($tl->id);
        $chuaCo = Tour::create(['slug' => 'cat-tien', 'name' => 'Cát Tiên', 'type' => 'domestic', 'status' => 'published', 'adult_price' => 1]);

        $trang = Livewire::test(ListTours::class)->assertTableFilterExists('danh_muc');

        $trang->filterTable('danh_muc', [$mt->id])
            ->assertCanSeeTableRecords([$nhaTrang])
            ->assertCanNotSeeTableRecords([$bangkok, $chuaCo]);

        $trang->filterTable('danh_muc', [$mt->id, $tl->id])
            ->assertCanSeeTableRecords([$nhaTrang, $bangkok])
            ->assertCanNotSeeTableRecords([$chuaCo]);

        $trang->filterTable('danh_muc', ['khong'])
            ->assertCanSeeTableRecords([$chuaCo])
            ->assertCanNotSeeTableRecords([$nhaTrang, $bangkok]);
    }
}
