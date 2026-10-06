<?php

namespace Tests\Feature;

use App\Filament\Resources\Guides\Pages\EditGuide;
use App\Filament\Resources\Tours\Pages\EditTour;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Modules\Guide\Models\Guide;
use Modules\Tour\Models\Tour;
use Tests\TestCase;

/**
 * "Tiêu đề SEO": nhân viên tự viết tiêu đề ngắn cho Google (lỗi "Long title
 * element" của công cụ audit). Trống thì website dùng tên tour / tiêu đề bài.
 */
class TieuDeSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_tour_tra_tieu_de_seo(): void
    {
        $tour = Tour::create([
            'slug' => 'da-nang-hoi-an', 'name' => 'TOUR SIÊU TIẾT KIỆM: ĐÀ NẴNG – HỘI AN – BÀ NÀ HILLS – CẦU VÀNG – SƠN TRÀ',
            'seo_title' => 'Tour Đà Nẵng – Hội An – Bà Nà giá tiết kiệm',
            'type' => 'domestic', 'status' => 'published', 'adult_price' => 1,
        ]);

        $this->getJson('/api/v1/tours/'.$tour->slug)
            ->assertOk()
            ->assertJsonPath('data.seo_title', 'Tour Đà Nẵng – Hội An – Bà Nà giá tiết kiệm');
    }

    public function test_api_bai_cam_nang_uu_tien_tieu_de_seo_trong_thi_dung_tieu_de(): void
    {
        $co = Guide::create(['title' => 'Kinh nghiệm du lịch Đà Lạt mấy ngày là đủ cho gia đình có trẻ nhỏ', 'seo_title' => 'Du lịch Đà Lạt mấy ngày là đủ?', 'slug' => 'da-lat', 'status' => 'published', 'published_at' => now()]);
        $khong = Guide::create(['title' => 'Phú Quốc tháng mấy đẹp', 'slug' => 'phu-quoc', 'status' => 'published', 'published_at' => now()]);

        $this->getJson('/api/v1/guides/'.$co->slug)->assertOk()->assertJsonPath('data.meta_title', 'Du lịch Đà Lạt mấy ngày là đủ?');
        $this->getJson('/api/v1/guides/'.$khong->slug)->assertOk()->assertJsonPath('data.meta_title', 'Phú Quốc tháng mấy đẹp');
    }

    public function test_form_admin_luu_tieu_de_seo_va_chan_qua_70_ky_tu(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $nv = User::factory()->create();
        foreach (['View:Tour', 'ViewAny:Tour', 'Update:Tour', 'View:Guide', 'ViewAny:Guide', 'Update:Guide'] as $q) {
            $nv->givePermissionTo(Permission::findOrCreate($q, 'web'));
        }
        $this->actingAs($nv);

        $tour = Tour::create(['slug' => 'tour-a', 'name' => 'Tour A', 'type' => 'domestic', 'status' => 'published', 'adult_price' => 1]);
        Livewire::test(EditTour::class, ['record' => $tour->getRouteKey()])
            ->assertFormFieldExists('seo_title')
            ->fillForm(['seo_title' => str_repeat('a', 71)])
            ->call('save')
            ->assertHasFormErrors(['seo_title' => 'max']);
        Livewire::test(EditTour::class, ['record' => $tour->getRouteKey()])
            ->fillForm(['seo_title' => 'Tour A giá tốt'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('Tour A giá tốt', $tour->fresh()->seo_title);

        $bai = Guide::create(['title' => 'Bài A', 'slug' => 'bai-a', 'status' => 'published', 'published_at' => now()]);
        Livewire::test(EditGuide::class, ['record' => $bai->getRouteKey()])
            ->assertFormFieldExists('seo_title')
            ->fillForm(['seo_title' => 'Bài A ngắn gọn'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('Bài A ngắn gọn', $bai->fresh()->seo_title);
    }
}
