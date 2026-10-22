<?php

namespace Tests\Feature;

use App\Filament\Pages\NguonKhachHang;
use App\Models\ContactMessage;
use App\Models\User;
use App\Services\NguonKhach;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Booking\Models\Booking;
use Modules\Tour\Models\Tour;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Ghi kênh khách đến (TikTok, Google, ChatGPT…) vào đơn và tin liên hệ, rồi
 * thống kê theo kênh / clip — biết clip nào ra đơn, AI có đang trích dẫn PSV.
 */
class NguonKhachTest extends TestCase
{
    use RefreshDatabase;

    public function test_phan_loai_kenh(): void
    {
        $k = fn (array $u) => NguonKhach::tuDuLieu($u)['kenh'];

        $this->assertSame('tiktok', $k(['source' => 'tiktok', 'content' => 'clip-01']));
        $this->assertSame('chatgpt', $k(['source' => 'chatgpt.com'])); // ChatGPT tự gắn khi dẫn link
        $this->assertSame('perplexity', $k(['referrer' => 'https://www.perplexity.ai/search?q=tour']));
        $this->assertSame('gemini', $k(['referrer' => 'gemini.google.com']));
        $this->assertSame('google', $k(['referrer' => 'https://www.google.com.vn/']));
        $this->assertSame('google_ads', $k(['referrer' => 'google.com', 'gclid' => 'abc']));
        $this->assertSame('facebook_ads', $k(['source' => 'facebook', 'medium' => 'cpc']));
        $this->assertSame('facebook', $k(['referrer' => 'l.facebook.com', 'fbclid' => 'x']));
        $this->assertSame('zalo', $k(['referrer' => 'chat.zalo.me']));
        $this->assertSame('web_khac', $k(['referrer' => 'vnexpress.net']));
        $this->assertSame('khac', $k(['source' => 'doi-tac-abc']));
        $this->assertSame('truc_tiep', $k(['landing' => '/']));
        $this->assertSame('truc_tiep', $k([])); // có gửi nhưng không nguồn nào = vào thẳng
        $this->assertNull(NguonKhach::tuDuLieu(null)['kenh']); // không gửi = không rõ

        // Chỉ giữ tên miền giới thiệu, cắt chuỗi dài, bỏ thẻ HTML
        $u = NguonKhach::tuDuLieu(['referrer' => 'https://www.google.com/search?q=so+dien+thoai+0909', 'campaign' => '<b>tet</b>'.str_repeat('x', 300)])['utm'];
        $this->assertSame('google.com', $u['referrer']);
        $this->assertSame(150, mb_strlen($u['campaign']));
        $this->assertStringNotContainsString('<b>', $u['campaign']);
    }

    public function test_don_va_tin_lien_he_luu_kenh_va_bao_cao_theo_thang(): void
    {
        $tour = Tour::create(['slug' => 'da-lat', 'name' => 'Đà Lạt', 'type' => 'domestic', 'status' => 'published', 'adult_price' => 2_000_000, 'duration_days' => 3]);
        $don = fn (array $nguon) => $this->postJson('/api/v1/bookings', [
            'tour_id' => $tour->id, 'customer_name' => 'Nguyễn Văn A', 'customer_phone' => '0909'.random_int(100000, 999999),
            'adults' => 2, 'nguon' => $nguon,
        ])->assertCreated();

        $don(['source' => 'tiktok', 'campaign' => 'tet-2027', 'content' => 'clip-bac-kinh-01']);
        $don(['source' => 'tiktok', 'campaign' => 'tet-2027', 'content' => 'clip-bac-kinh-01']);
        $don(['source' => 'chatgpt.com']);
        $this->postJson('/api/v1/bookings', ['tour_id' => $tour->id, 'customer_name' => 'B', 'customer_phone' => '0909111222', 'adults' => 1])->assertCreated();
        $this->postJson('/api/v1/team-building', ['name' => 'Chị C', 'phone' => '0909333444', 'company' => 'ABC', 'nguon' => ['referrer' => 'https://www.perplexity.ai/']])->assertSuccessful();

        $ds = Booking::orderBy('id')->get();
        $this->assertSame(['tiktok', 'tiktok', 'chatgpt', null], $ds->pluck('kenh')->all());
        $this->assertSame('clip-bac-kinh-01', $ds[0]->utm['content']);
        $this->assertSame('perplexity', ContactMessage::sole()->kenh);

        // Một đơn TikTok được chốt
        $ds[0]->forceFill(['status' => 'confirmed'])->save();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $mk = User::factory()->create();
        $mk->assignRole(Role::findOrCreate('marketing', 'web'));
        $this->actingAs($mk);

        $trang = Livewire::test(NguonKhachHang::class)->assertOk()
            ->assertSee('TikTok')->assertSee('clip-bac-kinh-01')->assertSee('Trong đó: trợ lý AI');
        $kenh = $trang->instance()->theoKenh()->keyBy('ten');
        $this->assertSame(['yeu_cau' => 0, 'don' => 2, 'chot' => 1, 'khach' => 2, 'doanh_so' => 4_000_000],
            array_intersect_key($kenh['TikTok'], array_flip(['yeu_cau', 'don', 'chot', 'khach', 'doanh_so'])));
        $this->assertSame(1, $kenh['Trong đó: trợ lý AI']['don']);
        $this->assertSame(1, $kenh['Trong đó: trợ lý AI']['yeu_cau']);
        $this->assertSame(1, $kenh['Không rõ']['don']);
        $this->assertSame(4, $kenh['Tổng']['don']);

        // Nhân viên thường không mở được trang này
        $this->actingAs(User::factory()->create());
        $this->assertFalse(NguonKhachHang::canAccess());
    }
}
