<?php

namespace Tests\Feature;

use App\Filament\Resources\Guides\Pages\EditGuide;
use App\Models\Redirect;
use App\Models\User;
use App\Services\KiemTraSeoBai;
use App\Services\SuaLinkNoiDung;
use App\Services\ThuNhoAnh;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Guide\Models\Guide;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Các lỗi Ahrefs báo trên bài cẩm nang: ảnh quá nặng, ảnh thiếu alt, link đi
 * vòng qua chuyển hướng, đường dẫn bài sai định dạng.
 */
class NoiDungBaiSeoTest extends TestCase
{
    use RefreshDatabase;

    private function bai(array $them = []): Guide
    {
        return Guide::create(['title' => 'Cẩm nang Osaka', 'slug' => 'cam-nang-osaka', 'status' => 'published',
            'published_at' => now()->subDay(), ...$them]);
    }

    /** JPEG thử: dải màu + nét vẽ cho có dung lượng; $huong = thẻ EXIF Orientation */
    private function jpeg(int $rong, int $cao, int $huong = 1): string
    {
        $a = imagecreatetruecolor($rong, $cao);
        for ($x = 0; $x < $rong; $x += 4) {
            imagefilledrectangle($a, $x, 0, $x + 3, $cao, imagecolorallocate($a, $x % 256, ($x * 3) % 256, 120));
        }
        mt_srand(7);
        for ($i = 0; $i < 3000; $i++) {
            imageline($a, mt_rand(0, $rong), mt_rand(0, $cao), mt_rand(0, $rong), mt_rand(0, $cao), imagecolorallocate($a, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
        }
        ob_start();
        imagejpeg($a, null, 95);
        $du = ob_get_clean();
        if ($huong === 1) {
            return $du;
        }
        $tiff = 'II'.pack('v', 42).pack('V', 8).pack('v', 1).pack('vvVvv', 0x0112, 3, 1, $huong, 0).pack('V', 0);
        $app1 = "Exif\0\0".$tiff;

        return "\xFF\xD8\xFF\xE1".pack('n', strlen($app1) + 2).$app1.substr($du, 2);
    }

    public function test_anh_chen_trong_bai_tu_thu_nho_khi_luu_va_giu_ban_goc(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Storage::disk('public')->put('guides/noi-dung/to.jpg', $this->jpeg(3200, 1800));
        // Ảnh lưu 1200×2600 kèm thẻ EXIF "xoay 90°" → hiện ra rộng 2600, cao 1200
        Storage::disk('public')->put('guides/noi-dung/doc.jpg', $this->jpeg(1200, 2600, 6));
        Storage::disk('public')->put('guides/noi-dung/nho.jpg', $this->jpeg(400, 300));
        $this->assertSame(6, ThuNhoAnh::huongExif(Storage::disk('public')->path('guides/noi-dung/doc.jpg')));
        $nhoTruoc = Storage::disk('public')->size('guides/noi-dung/nho.jpg');

        $this->bai(['content' => '<p><img src="https://api.psvtravel.com/storage/guides/noi-dung/to.jpg"></p>'
            .'<p><img src="https://api.psvtravel.com/storage/guides/noi-dung/doc.jpg"><img src="https://api.psvtravel.com/storage/guides/noi-dung/nho.jpg"></p>']);

        [$r, $c] = getimagesize(Storage::disk('public')->path('guides/noi-dung/to.jpg'));
        $this->assertSame([2000, 1125], [$r, $c]);
        // Ảnh có thẻ xoay: đã xoay thật điểm ảnh rồi thu về 2000px, bỏ thẻ EXIF
        $this->assertSame([2000, 923], array_slice(getimagesize(Storage::disk('public')->path('guides/noi-dung/doc.jpg')), 0, 2));
        $this->assertSame(1, ThuNhoAnh::huongExif(Storage::disk('public')->path('guides/noi-dung/doc.jpg')));
        // Ảnh nhỏ để nguyên; bản gốc ảnh to cất ở ổ riêng
        $this->assertSame($nhoTruoc, Storage::disk('public')->size('guides/noi-dung/nho.jpg'));
        $this->assertSame([3200, 1800], array_slice(getimagesize(Storage::disk('local')->path('anh-goc/guides/noi-dung/to.jpg')), 0, 2));
        $this->assertFalse(Storage::disk('local')->exists('anh-goc/guides/noi-dung/nho.jpg'));

        // Lệnh cho ảnh cũ: chạy lại thì không còn gì phải làm
        $this->artisan('psv:thu-nho-anh', ['--xem' => true])->expectsOutputToContain('Không có ảnh nào cần thu nhỏ')->assertSuccessful();
    }

    public function test_link_noi_bo_trong_bai_tro_thang_trang_dich_khi_luu(): void
    {
        Redirect::create(['old_path' => '/tour_nuoc_ngoai/osaka-cu', 'new_path' => '/tour-nuoc-ngoai/nhat-ban-osaka', 'status_code' => 301, 'source' => 'tay']);
        Redirect::create(['old_path' => '/cam-nang/bai-da-go', 'new_path' => null, 'status_code' => 410, 'source' => 'tay']);
        $g = $this->bai(['content' => '<p><a href="https://www.psvtravel.com/cam-nang/kyoto/">Kyoto</a> '
            .'<a href="http://psvtravel.com.vn/tour_nuoc_ngoai/osaka-cu/?utm_source=x&amp;b=1">tour</a> '
            .'<a href="/cam-nang/bai-da-go">gỡ</a> <a href="https://www.google.com/maps">bản đồ</a> <a href="tel:0907870707">gọi</a></p>']);

        $this->assertSame('<p><a href="/cam-nang/kyoto">Kyoto</a> <a href="/tour-nuoc-ngoai/nhat-ban-osaka?utm_source=x&amp;b=1">tour</a> '
            .'<a href="/cam-nang/bai-da-go">gỡ</a> <a href="https://www.google.com/maps">bản đồ</a> <a href="tel:0907870707">gọi</a></p>', $g->fresh()->content);
        $this->assertSame([['link' => '/cam-nang/bai-da-go', 'ma' => 410]], SuaLinkNoiDung::sua($g->content)['hong']);

        // Bài lưu từ trước (không qua bước lưu mới): lệnh sửa hàng loạt
        Guide::query()->whereKey($g->id)->update(['content' => '<a href="https://psvtravel.com/lien-he/">liên hệ</a>']);
        $this->artisan('psv:sua-link-noi-dung', ['--xem' => true])->expectsOutputToContain('Sẽ sửa 1 link trong 1 bài')->assertSuccessful();
        $this->assertSame('<a href="https://psvtravel.com/lien-he/">liên hệ</a>', $g->fresh()->content);
        $this->artisan('psv:sua-link-noi-dung')->assertSuccessful();
        $this->assertSame('<a href="/lien-he">liên hệ</a>', $g->fresh()->content);
    }

    public function test_slug_sai_dinh_dang_duoc_sua_va_link_cu_chuyen_huong(): void
    {
        $g = $this->bai(['slug' => 'Tempura Nhật Bản: Nguồn Gốc, Các Loại Và Cách Ăn', 'title' => 'Tempura Nhật Bản']);
        $this->bai(['slug' => 'tempura-nhat-ban-nguon-goc-cac-loai-va-cach-an', 'title' => 'Bài trùng đường dẫn mới']);
        $this->assertStringContainsString('sai định dạng', implode("\n", array_column(KiemTraSeoBai::goiY($g), 'noi_dung')));

        $this->artisan('psv:sua-slug', ['--xem' => true])->expectsOutputToContain('Sẽ sửa 1 đường dẫn')->assertSuccessful();
        $this->artisan('psv:sua-slug')->assertSuccessful();
        $this->assertSame('tempura-nhat-ban-nguon-goc-cac-loai-va-cach-an-2', $g->fresh()->slug);

        // Website tải danh sách này để chuyển 301 link cũ (có dấu, có khoảng trắng)
        $ds = $this->getJson('/api/v1/link-cu/doi-duong-dan')->json('data');
        $this->assertSame('/cam-nang/tempura-nhat-ban-nguon-goc-cac-loai-va-cach-an-2', $ds['/cam-nang/Tempura Nhật Bản: Nguồn Gốc, Các Loại Và Cách Ăn']);
    }

    public function test_goi_y_seo_bai_va_khung_trong_form(): void
    {
        $g = $this->bai(['excerpt' => 'Ngắn quá.', 'content' => '<h2>Ăn gì</h2><img src="/storage/guides/noi-dung/a.jpg"><img src="/storage/guides/noi-dung/b.jpg" alt="Takoyaki">'
            .'<a href="https://www.psvtravel.com/lien-he/">x</a>']);
        Guide::query()->whereKey($g->id)->update(['content' => $g->content.'<a href="https://www.psvtravel.com/lien-he/">x</a>']);
        $goiY = implode("\n", array_column(KiemTraSeoBai::goiY($g->fresh()), 'noi_dung'));
        $this->assertStringContainsString('1/2 ảnh trong bài chưa có mô tả (alt)', $goiY);
        $this->assertStringContainsString('mới 9 ký tự', $goiY);
        $this->assertStringContainsString('1 link trong bài đi vòng qua chuyển hướng', $goiY);
        $this->assertStringContainsString('Chưa có ảnh bìa', $goiY);
        $this->assertSame('Cẩm nang Osaka | PSV Travel', KiemTraSeoBai::tieuDeGoogle($g));

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $nv = User::factory()->create();
        foreach (['ViewAny', 'View', 'Update'] as $q) {
            $nv->givePermissionTo(Permission::findOrCreate("{$q}:Guide", 'web'));
        }
        $this->actingAs($nv);
        Livewire::test(EditGuide::class, ['record' => $g->getRouteKey()])
            ->assertOk()->assertSee('Gợi ý SEO')->assertSee('chưa có mô tả (alt)')
            ->fillForm(['slug' => 'Sai Định Dạng'])->call('save')->assertHasFormErrors(['slug' => 'regex']);
    }
}
