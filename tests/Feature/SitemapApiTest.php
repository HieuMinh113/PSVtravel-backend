<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Guide\Models\Guide;
use Modules\Tour\Models\Tour;
use Modules\Tour\Models\TourDeparture;
use Modules\Visa\Models\VisaDeadline;
use Tests\TestCase;

/**
 * API cho sitemap.xml: đủ MỌI tour / bài đang hiển thị — danh sách thường chỉ trả
 * 50 tour, 30 bài mỗi trang và bỏ tour tạm ẩn vì hết hạn visa, sitemap sẽ sót.
 */
class SitemapApiTest extends TestCase
{
    use RefreshDatabase;

    private function tour(string $slug, array $them = []): Tour
    {
        return Tour::create(['slug' => $slug, 'name' => 'Tour '.$slug, 'type' => 'domestic', 'status' => 'published',
            'adult_price' => 1_000_000, 'duration_days' => 3, ...$them]);
    }

    public function test_sitemap_tour_du_moi_tour_ke_ca_tour_tam_an_vi_het_han_visa(): void
    {
        $this->travelTo(Carbon::parse('2026-11-14 09:00'));
        foreach (range(1, 55) as $i) {
            $this->tour("trong-nuoc-{$i}");
        }
        $this->tour('nhap', ['status' => 'draft']);
        $this->tour('da-xoa')->delete();

        // Tour nước ngoài mọi đợt đã quá hạn visa: ẩn khỏi danh sách nhưng trang vẫn mở
        VisaDeadline::create(['country' => 'Trung Quốc', 'days' => 7]);
        $bk = $this->tour('bac-kinh', ['type' => 'abroad', 'country' => 'Trung Quốc']);
        TourDeparture::create(['tour_id' => $bk->id, 'start_date' => '2026-11-18', 'seats_total' => 20, 'seats_left' => 20]);
        $this->assertNotContains('bac-kinh', array_column($this->getJson('/api/v1/tours?type=abroad')->json('data'), 'slug'));

        $ds = collect($this->getJson('/api/v1/tours-sitemap')->assertOk()->json('data'));
        $this->assertCount(56, $ds);
        $this->assertSame('abroad', $ds->firstWhere('slug', 'bac-kinh')['type']);
        $this->assertNotNull($ds->firstWhere('slug', 'trong-nuoc-55')['updated_at']);
        $this->assertNull($ds->firstWhere('slug', 'nhap'));
        $this->assertNull($ds->firstWhere('slug', 'da-xoa'));
        // Danh sách thường vẫn giới hạn 50
        $this->assertCount(50, $this->getJson('/api/v1/tours?type=domestic&per_page=50')->json('data'));
    }

    public function test_sitemap_cam_nang_du_moi_bai_dang_hien(): void
    {
        foreach (range(1, 35) as $i) {
            Guide::create(['title' => "Bài {$i}", 'slug' => "bai-{$i}", 'status' => 'published', 'published_at' => now()->subDays($i)]);
        }
        Guide::create(['title' => 'Nháp', 'slug' => 'nhap', 'status' => 'draft']);
        Guide::create(['title' => 'Hẹn đăng', 'slug' => 'hen-dang', 'status' => 'published', 'published_at' => now()->addDay()]);

        $ds = collect($this->getJson('/api/v1/guides-sitemap')->assertOk()->json('data'));
        $this->assertCount(35, $ds);
        $this->assertNotNull($ds->firstWhere('slug', 'bai-35'));
        $this->assertNull($ds->firstWhere('slug', 'nhap'));
        $this->assertNull($ds->firstWhere('slug', 'hen-dang'));
    }
}
