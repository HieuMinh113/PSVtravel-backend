<?php

namespace Tests\Feature;

use App\Filament\Pages\TrangTeamBuilding;
use App\Models\Event;
use App\Models\EventReview;
use App\Models\User;
use App\Services\NoiDungTeamBuilding;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Page\Models\Setting;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Trang giới thiệu Team Building: Marketing sửa nội dung trong admin, website
 * đọc qua /api/v1/team-building-page (ảnh đủ đường dẫn, chỉ logo đang bật,
 * đánh giá đã duyệt), ảnh gỡ khỏi trang thì xoá khỏi ổ.
 */
class TrangTeamBuildingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function nguoi(string $vaiTro): User
    {
        return User::factory()->create()->assignRole(Role::findOrCreate($vaiTro, 'web'));
    }

    public function test_marketing_sua_duoc_nhan_vien_khac_khong_vao_duoc(): void
    {
        $this->actingAs($this->nguoi('marketing'))->get('/admin/trang-team-building')
            ->assertOk()->assertSee('Khách hàng đã đồng hành')->assertSee('Xem trang trên website');
        $this->actingAs($this->nguoi('sale'))->get('/admin/trang-team-building')->assertForbidden();
    }

    public function test_luu_noi_dung_tu_admin(): void
    {
        $this->actingAs($this->nguoi('marketing'));
        Livewire::test(TrangTeamBuilding::class)
            ->set('data.hero_tieu_de', 'Gắn kết đội ngũ cùng PSV')
            ->set('data.hero_video_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
            ->set('data.gt_noi_dung', "Đoạn một.\nĐoạn hai.")
            ->set('data.so_lieu', [['so' => '120+', 'nhan' => 'đoàn mỗi năm']])
            ->set('data.loai_hinh', [['ten' => 'Team building bãi biển', 'mo_ta' => 'Vũng Tàu, Phan Thiết', 'anh' => null]])
            ->call('luu')
            ->assertHasNoErrors();

        $d = NoiDungTeamBuilding::congKhai();
        $this->assertSame('Gắn kết đội ngũ cùng PSV', $d['hero']['tieu_de']);
        $this->assertSame('dQw4w9WgXcQ', $d['hero']['video_id']);
        $this->assertSame("Đoạn một.\nĐoạn hai.", $d['gioi_thieu']['noi_dung']);
        $this->assertSame([['so' => '120+', 'nhan' => 'đoàn mỗi năm']], $d['so_lieu']);
        $this->assertSame('Team building bãi biển', $d['loai_hinh'][0]['ten']);
        $this->assertNull($d['loai_hinh'][0]['anh']);

        // Mở lại trang admin: nội dung vừa lưu hiện đúng
        Livewire::test(TrangTeamBuilding::class)
            ->assertSet('data.hero_tieu_de', 'Gắn kết đội ngũ cùng PSV')
            ->assertSet('data.so_lieu', fn ($v) => array_values($v)[0]['so'] === '120+');
    }

    public function test_api_cho_website_chi_logo_dang_bat_va_danh_gia_da_duyet(): void
    {
        foreach (['team-building/hero.jpg', 'team-building/a.jpg', 'team-building/b.jpg', 'team-building/logo-1.png', 'team-building/logo-2.png'] as $t) {
            Storage::disk('public')->put($t, 'x');
        }
        NoiDungTeamBuilding::luu([
            'hero' => ['anh' => 'team-building/hero.jpg', 'video_url' => 'khong-phai-link', 'tieu_de' => '  ', 'mo_ta' => null],
            'gioi_thieu' => [],
            'so_lieu' => [['so' => '', 'nhan' => 'thiếu số']],
            'loai_hinh' => [['ten' => '', 'mo_ta' => 'không tên']],
            'khoanh_khac' => ['team-building/a.jpg', 'team-building/b.jpg'],
            'khach_hang' => [
                ['ten' => 'Công ty A', 'logo' => 'team-building/logo-1.png', 'hien' => true],
                ['ten' => 'Công ty B', 'logo' => 'team-building/logo-2.png', 'hien' => false],
            ],
        ]);

        $goi = Event::create(['title' => 'Team building Vũng Tàu', 'slug' => 'tb-vung-tau', 'status' => 'published']);
        $an = Event::create(['title' => 'Gói đã ẩn', 'slug' => 'goi-an', 'status' => 'draft']);
        EventReview::create(['event_id' => $goi->id, 'customer_name' => 'Chị Lan', 'rating' => 5, 'content' => 'Chương trình rất vui', 'status' => 'approved']);
        EventReview::create(['event_id' => $goi->id, 'customer_name' => 'Chờ duyệt', 'rating' => 4, 'content' => 'x', 'status' => 'pending']);
        EventReview::create(['event_id' => $an->id, 'customer_name' => 'Gói ẩn', 'rating' => 5, 'content' => 'y', 'status' => 'approved']);

        $d = $this->getJson('/api/v1/team-building-page')->assertOk()->json('data');
        $this->assertStringEndsWith('/storage/team-building/hero.jpg', $d['hero']['anh']);
        $this->assertNull($d['hero']['video_id']);
        $this->assertNull($d['hero']['tieu_de']); // chỉ khoảng trắng = để trống
        $this->assertSame([], $d['so_lieu']);
        $this->assertSame([], $d['loai_hinh']);
        $this->assertCount(2, $d['khoanh_khac']);
        $this->assertSame(['Công ty A'], array_column($d['khach_hang'], 'ten'));
        $this->assertSame([['ten' => 'Chị Lan', 'diem' => 5, 'noi_dung' => 'Chương trình rất vui', 'goi' => 'Team building Vũng Tàu', 'goi_slug' => 'tb-vung-tau']], $d['danh_gia']);

        // Nội dung trang không lọt vào cài đặt công khai
        $this->assertArrayNotHasKey(NoiDungTeamBuilding::KHOA, $this->getJson('/api/v1/settings')->json('data'));
    }

    public function test_go_anh_khoi_trang_thi_xoa_khoi_o(): void
    {
        foreach (['team-building/cu.jpg', 'team-building/giu.jpg', 'events/anh-goi.jpg'] as $t) {
            Storage::disk('public')->put($t, 'x');
        }
        NoiDungTeamBuilding::luu(['khoanh_khac' => ['team-building/cu.jpg', 'team-building/giu.jpg'], 'hero' => ['anh' => 'events/anh-goi.jpg']]);
        NoiDungTeamBuilding::luu(['khoanh_khac' => ['team-building/giu.jpg']]);

        Storage::disk('public')->assertMissing('team-building/cu.jpg');
        Storage::disk('public')->assertExists('team-building/giu.jpg');
        Storage::disk('public')->assertExists('events/anh-goi.jpg'); // ngoài thư mục trang này: không đụng
        $this->assertSame('team_building', Setting::where('key', NoiDungTeamBuilding::KHOA)->value('group'));
    }
}
