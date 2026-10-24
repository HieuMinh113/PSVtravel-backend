<?php

namespace Tests\Feature;

use App\Filament\Resources\Tours\Pages\EditTour;
use App\Models\Redirect;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\KiemTraSeoTour;
use App\Services\LinkCu;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Once;
use Livewire\Livewire;
use Modules\Guide\Models\Guide;
use Modules\Tour\Models\Tour;
use Modules\Tour\Models\TourItinerary;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Trang tác giả cẩm nang (E-E-A-T), chuyển hướng khi đổi đường dẫn, gợi ý SEO
 * trong form tour.
 */
class TacGiaVaSeoTourTest extends TestCase
{
    use RefreshDatabase;

    private function bai(string $slug, ?TeamMember $tg, array $them = []): Guide
    {
        return Guide::create(['title' => 'Bài '.$slug, 'slug' => $slug, 'status' => 'published', 'published_at' => now()->subDay(),
            'team_member_id' => $tg?->id, ...$them]);
    }

    public function test_trang_tac_gia_chi_hien_nguoi_co_bai_va_khong_lo_email(): void
    {
        $minh = TeamMember::create(['name' => 'Nguyễn Văn Minh', 'position' => 'Trưởng phòng tour Nhật', 'email' => 'minh@vi-du.vn',
            'status' => 'published', 'sort_order' => 1, 'years_experience' => 8, 'expertise' => 'Tuyến Nhật Bản',
            'countries' => ['Nhật Bản', ' Hàn Quốc ', ''], 'profile' => 'Anh Minh có 8 năm dẫn tour Nhật Bản.',
            'social_links' => ['https://www.facebook.com/minh', 'javascript:alert(1)']]);
        $chuaViet = TeamMember::create(['name' => 'Trần Thị Lan', 'position' => 'Sale', 'status' => 'published', 'sort_order' => 2]);
        $an = TeamMember::create(['name' => 'Người Ẩn', 'position' => 'Visa', 'status' => 'hidden', 'sort_order' => 3]);
        $this->assertSame('nguyen-van-minh', $minh->slug);
        $this->assertSame('nguyen-van-minh-2', TeamMember::create(['name' => 'Nguyễn Văn Minh', 'position' => 'x', 'status' => 'hidden', 'sort_order' => 9])->slug);

        $this->bai('kinh-nghiem-nhat-ban', $minh);
        $this->bai('nhap', $minh, ['status' => 'draft']);
        $this->bai('bai-cua-nguoi-an', $an);

        $ds = $this->getJson('/api/v1/authors')->assertOk()->json('data');
        $this->assertSame(['nguyen-van-minh'], array_column($ds, 'slug'));

        $tg = $this->getJson('/api/v1/authors/nguyen-van-minh')->assertOk()->json('data');
        $this->assertSame(['Nhật Bản', 'Hàn Quốc'], $tg['countries']);
        $this->assertSame(['https://www.facebook.com/minh'], $tg['social_links']);
        $this->assertSame(8, $tg['years_experience']);
        $this->assertSame(['kinh-nghiem-nhat-ban'], array_column($tg['guides'], 'slug'));
        $this->assertArrayNotHasKey('email', $tg);
        $this->getJson('/api/v1/authors/'.$chuaViet->slug)->assertNotFound(); // chưa có bài: trang mỏng
        $this->getJson('/api/v1/authors/'.$an->slug)->assertNotFound();

        // Bài viết: khung tác giả + tên; bài không gắn tác giả giữ tên người nhập như trước
        $bai = $this->getJson('/api/v1/guides/kinh-nghiem-nhat-ban')->json('data');
        $this->assertSame(['nguyen-van-minh', 'Nguyễn Văn Minh', 'Trưởng phòng tour Nhật'], [$bai['author']['slug'], $bai['author']['name'], $bai['author']['position']]);
        $nv = User::factory()->create(['name' => 'Tài khoản nhập bài']);
        $this->bai('khong-tac-gia', null, ['author_id' => $nv->id]);
        $khong = $this->getJson('/api/v1/guides/khong-tac-gia')->json('data');
        $this->assertNull($khong['author']);
        $this->assertSame('Tài khoản nhập bài', $khong['author_name']);

        // Trang Về chúng tôi gắn link cho người có trang tác giả
        $doi = collect($this->getJson('/api/v1/team-members')->json('data'))->keyBy('name');
        $this->assertSame('nguyen-van-minh', $doi['Nguyễn Văn Minh']['author_slug']);
        $this->assertNull($doi['Trần Thị Lan']['author_slug']);
    }

    public function test_doi_duong_dan_tour_va_bai_tu_chuyen_huong_301(): void
    {
        $t = Tour::create(['slug' => 'thanhdo-cuutraicau', 'name' => 'Thành Đô - Cửu Trại Câu', 'type' => 'abroad', 'status' => 'published', 'adult_price' => 15_990_000]);
        $t->update(['slug' => 'thanh-do-cuu-trai-cau']);
        $this->assertDatabaseHas('redirects', ['old_path' => '/tour-nuoc-ngoai/thanhdo-cuutraicau', 'new_path' => '/tour-nuoc-ngoai/thanh-do-cuu-trai-cau', 'status_code' => 301, 'source' => 'doi_slug']);

        // Đổi lần nữa: link đầu tiên trỏ thẳng link mới nhất, không chuyển hai lần
        $t->update(['slug' => 'thanh-do-cuu-trai-cau-6n5d']);
        $this->assertSame('/tour-nuoc-ngoai/thanh-do-cuu-trai-cau-6n5d', Redirect::firstWhere('old_path', '/tour-nuoc-ngoai/thanhdo-cuutraicau')->new_path);
        $this->assertSame(['ma' => 301, 'toi' => '/tour-nuoc-ngoai/thanh-do-cuu-trai-cau-6n5d'], LinkCu::tra('/tour-nuoc-ngoai/thanh-do-cuu-trai-cau'));

        // Đổi về đường dẫn cũ: bỏ dòng đang chuyển nó đi (khỏi vòng lặp)
        $t->update(['slug' => 'thanh-do-cuu-trai-cau']);
        $this->assertDatabaseMissing('redirects', ['old_path' => '/tour-nuoc-ngoai/thanh-do-cuu-trai-cau']);

        // Đổi loại tour cũng đổi đường dẫn
        $t->update(['type' => 'domestic']);
        $this->assertDatabaseHas('redirects', ['old_path' => '/tour-nuoc-ngoai/thanh-do-cuu-trai-cau', 'new_path' => '/tour-trong-nuoc/thanh-do-cuu-trai-cau']);

        // Tour nháp chưa từng hiện trên web: không ghi
        $nhap = Tour::create(['slug' => 'nhap-a', 'name' => 'Nháp', 'type' => 'domestic', 'status' => 'draft']);
        $nhap->update(['slug' => 'nhap-b']);
        $this->assertDatabaseMissing('redirects', ['old_path' => '/tour-trong-nuoc/nhap-a']);

        // Bài cẩm nang
        $b = $this->bai('bai-cu', null);
        $b->update(['slug' => 'bai-moi']);
        $this->assertDatabaseHas('redirects', ['old_path' => '/cam-nang/bai-cu', 'new_path' => '/cam-nang/bai-moi']);

        // "Tự khớp lại" của bảng link cũ không xoá các dòng này
        foreach (Redirect::all() as $r) {
            LinkCu::khopLai($r);
        }
        $this->assertDatabaseHas('redirects', ['old_path' => '/cam-nang/bai-cu', 'new_path' => '/cam-nang/bai-moi']);

        // Danh sách website tải về để chuyển ở proxy: chỉ trang tour / cẩm nang,
        // bỏ link cũ nay lại là trang đang hiện (tour mới đặt trùng đường dẫn cũ)
        Redirect::create(['old_path' => '/tour_nuoc_ngoai/abc', 'new_path' => '/tour-nuoc-ngoai/thanh-do-cuu-trai-cau', 'status_code' => 301, 'source' => 'tu_dong']);
        $ds = $this->getJson('/api/v1/link-cu/doi-duong-dan')->assertOk()->json('data');
        $this->assertSame('/tour-trong-nuoc/thanh-do-cuu-trai-cau', $ds['/tour-nuoc-ngoai/thanh-do-cuu-trai-cau']);
        $this->assertSame('/cam-nang/bai-moi', $ds['/cam-nang/bai-cu']);
        $this->assertArrayNotHasKey('/tour_nuoc_ngoai/abc', $ds);
        Tour::create(['slug' => 'tour-song', 'name' => 'Tour đang bán', 'type' => 'domestic', 'status' => 'published']);
        Redirect::create(['old_path' => '/tour-trong-nuoc/tour-song', 'new_path' => '/tour-trong-nuoc/khac', 'status_code' => 301, 'source' => 'tay']);
        $this->assertArrayNotHasKey('/tour-trong-nuoc/tour-song', $this->getJson('/api/v1/link-cu/doi-duong-dan')->json('data'));
        $this->bai('bai-cu', null);
        $this->assertArrayNotHasKey('/cam-nang/bai-cu', $this->getJson('/api/v1/link-cu/doi-duong-dan')->json('data'));
    }

    public function test_goi_y_seo_tour_tinh_tieu_de_giong_website(): void
    {
        $t = Tour::create(['slug' => 'thanhdo-cuutraicau-giacoson', 'name' => 'Thành Đô - Cửu Trại Câu - Gia Cơ Sơn', 'type' => 'abroad', 'status' => 'published',
            'adult_price' => 15_990_000, 'duration_days' => 6, 'duration_nights' => 5, 'country' => 'Trung Quốc']);
        // Giống tieuDeTour + tieuDeSeo bên website: ghép số ngày, giá gọn, thêm đuôi nếu còn chỗ
        $this->assertSame('Thành Đô - Cửu Trại Câu - Gia Cơ Sơn 6N5Đ | Từ 15,99 triệu', KiemTraSeoTour::tieuDeGoogle($t));

        $goiY = implode("\n", array_column(KiemTraSeoTour::goiY($t), 'noi_dung'));
        $this->assertStringContainsString('"thanhdo" — nên tách "thanh-do"', $goiY);
        $this->assertStringContainsString('"cuutraicau" — nên tách "cuu-trai-cau"', $goiY);
        $this->assertStringContainsString('Chưa có lịch trình từng ngày', $goiY);
        $this->assertStringContainsString('chưa có số ngày', $goiY);
        $this->assertStringNotContainsString('chưa có tên điểm đến', $goiY); // "Thành Đô", "Cửu Trại Câu" là nơi

        $du = Tour::create(['slug' => 'da-nang-hoi-an-3n2d', 'name' => 'Đà Nẵng – Hội An 3N2Đ', 'type' => 'domestic', 'status' => 'published',
            'adult_price' => 3_990_000, 'duration_days' => 3, 'duration_nights' => 2, 'cover_image' => 'tours/a.jpg', 'departure_from' => 'TP.HCM',
            'highlights' => ['Phố cổ Hội An'], 'included' => ['Vé máy bay'], 'excluded' => ['Chi phí cá nhân'], 'cancellation_policy' => 'Huỷ trước 7 ngày hoàn 70%.']);
        TourItinerary::create(['tour_id' => $du->id, 'day_number' => 1, 'title' => 'Ngày 1', 'description' => 'Bay Đà Nẵng']);
        $this->assertSame('Đà Nẵng – Hội An 3N2Đ | Từ 3,99 triệu | PSV Travel', KiemTraSeoTour::tieuDeGoogle($du));
        $this->assertSame([], KiemTraSeoTour::goiY($du->fresh()));

        // Trùng tiêu đề với tour đang bán khác
        $trung = Tour::create(['slug' => 'da-nang-hoi-an-ban-2', 'name' => 'Đà Nẵng – Hội An 3N2Đ', 'type' => 'domestic', 'status' => 'published', 'adult_price' => 3_990_000, 'duration_days' => 3, 'duration_nights' => 2]);
        Once::flush(); // danh sách tiêu đề tính một lần mỗi lượt tải trang
        $this->assertStringContainsString('trùng tour', implode("\n", array_column(KiemTraSeoTour::goiY($trung), 'noi_dung')));

        // Form sửa tour có khung gợi ý
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $nv = User::factory()->create();
        foreach (['ViewAny', 'View', 'Create', 'Update'] as $q) {
            $nv->givePermissionTo(Permission::findOrCreate("{$q}:Tour", 'web'));
        }
        $this->actingAs($nv);
        Livewire::test(EditTour::class, ['record' => $t->getRouteKey()])
            ->assertOk()->assertSee('Gợi ý SEO')->assertSee('cuu-trai-cau')
            ->assertSee('link cũ tự chuyển sang link mới');
    }
}
