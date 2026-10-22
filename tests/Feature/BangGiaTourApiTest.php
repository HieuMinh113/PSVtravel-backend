<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Tour\Models\Tour;
use Modules\Tour\Models\TourDeparture;
use Modules\Visa\Models\VisaDeadline;
use Tests\TestCase;

/**
 * API cho /pricing.md và /llms.txt: đủ mọi tour khách đặt được (không giới hạn
 * 50), mỗi tour kèm 3 đợt gần nhất còn đặt được với đúng giá của đợt.
 */
class BangGiaTourApiTest extends TestCase
{
    use RefreshDatabase;

    private function tour(string $slug, array $them = []): Tour
    {
        return Tour::create(['slug' => $slug, 'name' => 'Tour '.$slug, 'type' => 'domestic', 'status' => 'published',
            'adult_price' => 1_000_000, 'duration_days' => 3, 'duration_nights' => 2, ...$them]);
    }

    private function dot(Tour $tour, string $ngay, array $them = []): TourDeparture
    {
        return TourDeparture::create(['tour_id' => $tour->id, 'start_date' => $ngay, 'seats_total' => 20, 'seats_left' => 20, ...$them]);
    }

    public function test_bang_gia_du_moi_tour_dat_duoc_kem_3_dot_gan_nhat(): void
    {
        $this->travelTo(Carbon::parse('2026-11-14 09:00'));
        foreach (range(1, 54) as $i) {
            $this->tour("trong-nuoc-{$i}");
        }
        $this->tour('nhap', ['status' => 'draft']);

        $dl = $this->tour('da-lat', ['departure_from' => 'Hồ Chí Minh', 'child_price' => 700_000, 'tag' => 'Bán chạy']);
        $this->dot($dl, '2026-11-10'); // đã đi
        $this->dot($dl, '2026-11-20', ['price_override' => 1_200_000, 'child_price_override' => 800_000]);
        $this->dot($dl, '2026-11-27');
        $this->dot($dl, '2026-12-04', ['status' => 'closed']);
        $this->dot($dl, '2026-12-11');
        $this->dot($dl, '2026-12-18');

        // Hàn Quốc visa đoàn 25 ngày: đợt 01/12 đã quá hạn, đợt 20/12 còn
        VisaDeadline::create(['country' => 'Hàn Quốc', 'kind' => 'doan', 'days' => 25]);
        $hq = $this->tour('han-quoc', ['type' => 'abroad', 'country' => 'Hàn Quốc', 'visa_kind' => 'doan', 'adult_price' => 13_990_000]);
        $this->dot($hq, '2026-12-01');
        $this->dot($hq, '2026-12-20');

        // Mọi đợt đã quá hạn visa → khách không đặt được → không vào bảng giá
        $bk = $this->tour('bac-kinh', ['type' => 'abroad', 'country' => 'Hàn Quốc', 'visa_kind' => 'doan']);
        $this->dot($bk, '2026-11-30');

        $ds = collect($this->getJson('/api/v1/tours-bang-gia')->assertOk()->json('data'));

        $this->assertCount(56, $ds);
        $this->assertNull($ds->firstWhere('slug', 'nhap'));
        $this->assertNull($ds->firstWhere('slug', 'bac-kinh'));

        $a = $ds->firstWhere('slug', 'da-lat');
        $this->assertSame('Hồ Chí Minh', $a['departure_from']);
        $this->assertSame('Bán chạy', $a['tag']);
        $this->assertSame(4, $a['departure_count']);
        $this->assertSame([
            ['start_date' => '2026-11-20', 'price' => 1_200_000, 'price_child' => 800_000, 'visa_deadline' => null],
            ['start_date' => '2026-11-27', 'price' => 1_000_000, 'price_child' => 700_000, 'visa_deadline' => null],
            ['start_date' => '2026-12-11', 'price' => 1_000_000, 'price_child' => 700_000, 'visa_deadline' => null],
        ], $a['departures']);

        $b = $ds->firstWhere('slug', 'han-quoc');
        $this->assertSame('Visa đoàn', $b['visa_kind_label']);
        // Đợt 20/12 ẩn từ 25/11 → ngày cuối nộp hồ sơ 24/11; đợt 01/12 đã quá hạn
        $this->assertSame([['start_date' => '2026-12-20', 'price' => 13_990_000, 'price_child' => null, 'visa_deadline' => '2026-11-24']], $b['departures']);

        // Tour chưa có lịch (bán theo yêu cầu) vẫn có giá, danh sách đợt rỗng
        $this->assertSame([], $ds->firstWhere('slug', 'trong-nuoc-1')['departures']);
    }
}
