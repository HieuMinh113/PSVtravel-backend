<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banner\Models\Banner;
use Tests\TestCase;

/**
 * Website hiện MỌI banner / poster đang chạy thành băng chuyền, nên thứ tự phải
 * cố định: theo "Thứ tự", cùng số thì banner tạo trước đứng trước.
 */
class BannerApiTest extends TestCase
{
    use RefreshDatabase;

    private function banner(string $tieuDe, array $them = []): Banner
    {
        return Banner::create(['position' => 'promo', 'title' => $tieuDe, 'image' => 'banners/'.$tieuDe.'.jpg',
            'status' => 'published', 'sort_order' => 0, ...$them]);
    }

    public function test_tra_moi_banner_dang_chay_theo_thu_tu_roi_theo_ngay_tao(): void
    {
        $this->banner('c', ['sort_order' => 1]);
        $this->banner('a');
        $this->banner('an', ['status' => 'draft']);
        $this->banner('het-han', ['end_at' => now()->subDay()]);
        $this->banner('b');
        $this->banner('poster-1', ['position' => 'popup']);
        $this->banner('poster-2', ['position' => 'popup', 'image_mobile' => 'banners/poster-2-dt.jpg']);

        $this->assertSame(['a', 'b', 'c'], array_column($this->getJson('/api/v1/banners?position=promo')->json('data'), 'title'));

        $popup = $this->getJson('/api/v1/banners?position=popup')->json('data');
        $this->assertSame(['poster-1', 'poster-2'], array_column($popup, 'title'));
        // Không có ảnh điện thoại riêng thì trả ảnh máy tính (web so sánh để biết)
        $this->assertSame($popup[0]['image'], $popup[0]['image_mobile']);
        $this->assertStringEndsWith('banners/poster-2-dt.jpg', $popup[1]['image_mobile']);
    }
}
