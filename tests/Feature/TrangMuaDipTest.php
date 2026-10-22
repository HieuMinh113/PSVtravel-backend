<?php

namespace Tests\Feature;

use App\Filament\Resources\SeasonPages\Pages\CreateSeasonPage;
use App\Filament\Resources\SeasonPages\Pages\ListSeasonPages;
use App\Models\SeasonPage;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Modules\Tour\Models\Tour;
use Modules\Tour\Models\TourDeparture;
use Modules\Visa\Models\VisaDeadline;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Trang theo mùa / dịp (/tour-tet…): tour tự hiện khi có đợt đặt được trong
 * khoảng ngày của trang — không gắn tay, không lọt tour hết chỗ / quá hạn visa.
 */
class TrangMuaDipTest extends TestCase
{
    use RefreshDatabase;

    private function tour(string $slug, array $them = []): Tour
    {
        return Tour::create(['slug' => $slug, 'name' => 'Tour '.$slug, 'type' => 'domestic', 'status' => 'published',
            'adult_price' => 1_000_000, 'duration_days' => 3, 'duration_nights' => 2, ...$them]);
    }

    private function dot(Tour $t, string $ngay, array $them = []): void
    {
        TourDeparture::create(['tour_id' => $t->id, 'start_date' => $ngay, 'seats_total' => 20, 'seats_left' => 20, ...$them]);
    }

    public function test_api_tra_dung_tour_khoi_hanh_trong_dip(): void
    {
        $this->travelTo(Carbon::parse('2026-11-14 09:00'));
        SeasonPage::create(['name' => 'Tour Tết 2027', 'slug' => 'tour-tet', 'start_from' => '2027-02-01', 'start_to' => '2027-02-20',
            'intro' => 'Du xuân sớm.', 'faqs' => [['q' => 'Đặt sớm bao lâu?', 'a' => 'Trước 2 tháng.'], ['q' => 'Trống', 'a' => '']]]);
        SeasonPage::create(['name' => 'Nháp', 'slug' => 'tour-nhap', 'start_from' => '2027-01-01', 'start_to' => '2027-12-31', 'status' => 'hidden']);

        $muon = $this->tour('da-lat');
        $this->dot($muon, '2027-02-15');
        $this->dot($muon, '2027-03-01'); // ngoài dịp: không hiện trên thẻ
        $som = $this->tour('phu-quoc');
        $this->dot($som, '2027-02-05');
        $this->dot($this->tour('ngoai-dip'), '2027-03-10');
        $this->dot($this->tour('het-cho'), '2027-02-10', ['seats_left' => 0]);
        $this->dot($this->tour('tour-nhap', ['status' => 'draft']), '2027-02-10');
        // Visa đoàn Trung Quốc 30 ngày: đợt 01/02 hết hạn nộp từ 01/01 → hôm nay (14/11) vẫn đặt được
        VisaDeadline::create(['country' => 'Trung Quốc', 'kind' => 'doan', 'days' => 30]);
        $this->dot($this->tour('bac-kinh', ['type' => 'abroad', 'country' => 'Trung Quốc']), '2027-02-01');

        $p = $this->getJson('/api/v1/season-pages/tour-tet')->assertOk()->json('data');
        $this->assertSame(['bac-kinh', 'phu-quoc', 'da-lat'], array_column($p['tours'], 'slug')); // đợt sớm nhất trước
        $this->assertSame(['2027-02-15'], $p['tours'][2]['departure_dates']);
        $this->assertSame([['q' => 'Đặt sớm bao lâu?', 'a' => 'Trước 2 tháng.']], $p['faqs']);

        // Chỉ tour trong nước
        SeasonPage::where('slug', 'tour-tet')->update(['type' => 'domestic']);
        $this->assertSame(['phu-quoc', 'da-lat'], array_column($this->getJson('/api/v1/season-pages/tour-tet')->json('data.tours'), 'slug'));

        $this->getJson('/api/v1/season-pages/tour-nhap')->assertNotFound();
        $this->assertSame(['tour-tet'], array_column($this->getJson('/api/v1/season-pages')->json('data'), 'slug'));
    }

    public function test_admin_tao_trang_chan_duong_dan_sai_va_marketing_duoc_quan_ly(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $mk = User::factory()->create();
        $mk->assignRole(Role::findOrCreate('marketing', 'web'));
        $this->actingAs($mk);

        $form = ['name' => 'Tour hè', 'start_from' => '2027-06-01', 'start_to' => '2027-08-31', 'status' => 'published', 'sort_order' => 0];
        foreach (['tet' => 'regex', 'Tour-He' => 'regex', 'tour-trong-nuoc' => 'not_in'] as $slug => $loi) {
            Livewire::test(CreateSeasonPage::class)->fillForm([...$form, 'slug' => $slug])->call('create')->assertHasFormErrors(['slug' => $loi]);
        }
        Livewire::test(CreateSeasonPage::class)->fillForm([...$form, 'slug' => 'tour-he', 'start_to' => '2027-05-01'])
            ->call('create')->assertHasFormErrors(['start_to']);

        Livewire::test(CreateSeasonPage::class)->fillForm([...$form, 'slug' => 'tour-he'])->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseHas('season_pages', ['slug' => 'tour-he']);
        Livewire::test(ListSeasonPages::class)->assertOk()->assertSee('Tour hè');

        // Khách hàng không vào được
        $this->actingAs(User::factory()->create());
        $this->assertFalse(auth()->user()->can('viewAny', SeasonPage::class));
    }
}
