<?php

namespace Tests\Feature;

use App\Filament\Pages\TaoLinkChienDich;
use App\Models\ContactMessage;
use App\Models\User;
use App\Services\NguonKhach;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Link chiến dịch: utm đặt tên thống nhất, website phân loại đúng kênh, chỉ
 * tạo link tới trang của chính website, có mã QR tải về.
 */
class TaoLinkChienDichTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.frontend_url' => 'https://psvtravel.com']);
    }

    public function test_tao_link_dung_dinh_dang_va_kenh(): void
    {
        $this->assertSame(
            'https://psvtravel.com/tour-tet?utm_source=tiktok&utm_medium=paid&utm_campaign=tet-2027&utm_content=clip-01',
            TaoLinkChienDich::taoLink('https://www.psvtravel.com/tour-tet', 'tiktok', 'paid', 'Tết 2027', 'Clip 01'),
        );
        // Giữ tham số sẵn có, thay utm cũ; đường dẫn tương đối cũng được
        $this->assertSame(
            'https://psvtravel.com/tour-nuoc-ngoai?category=nhat-ban&utm_source=qr&utm_medium=qr&utm_campaign=ho-so-nang-luc',
            TaoLinkChienDich::taoLink('/tour-nuoc-ngoai?category=nhat-ban&utm_source=cu', 'qr', 'qr', 'Hồ sơ năng lực'),
        );
        $this->assertNull(TaoLinkChienDich::taoLink('https://vi-du.com/x', 'facebook', 'social', 'a'));

        // Website phân loại kênh đúng từ link tạo ra
        $kenh = fn (string $s, string $m) => NguonKhach::tuDuLieu(['source' => $s, 'medium' => $m, 'campaign' => 'x'])['kenh'];
        $this->assertSame('facebook_ads', $kenh('facebook', 'paid'));
        $this->assertSame('facebook', $kenh('facebook', 'social'));
        $this->assertSame('google_ads', $kenh('google', 'paid'));
        $this->assertSame('tiktok', $kenh('tiktok', 'paid'));
        $this->assertSame('zalo', $kenh('zalo', 'social'));
        $this->assertSame('qr', $kenh('qr', 'qr'));
        $this->assertSame('email', $kenh('email', 'email'));
        foreach (array_keys(TaoLinkChienDich::KENH) as $k) {
            $this->assertNotSame('khac', $kenh($k, 'social'), $k);
        }
    }

    public function test_marketing_tao_link_co_qr_va_goi_y_chien_dich_cu(): void
    {
        ContactMessage::create(['name' => 'A', 'phone' => '0900000000', 'message' => 'x', 'source' => 'contact',
            'kenh' => 'tiktok', 'utm' => ['source' => 'tiktok', 'campaign' => 'he-phu-quoc']]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $mk = User::factory()->create();
        $mk->assignRole(Role::findOrCreate('marketing', 'web'));
        $this->actingAs($mk);

        Livewire::test(TaoLinkChienDich::class)
            ->assertOk()
            ->assertSee('he-phu-quoc') // gợi ý tên chiến dịch đã dùng
            ->assertSee('Điền tên chiến dịch')
            ->fillForm(['trang' => 'https://psvtravel.com/team-building', 'kenh' => 'qr', 'chien_dich' => 'Hồ sơ năng lực'])
            ->assertSchemaStateSet(['loai' => 'qr'], 'form')
            ->assertSee('https://psvtravel.com/team-building?utm_source=qr&amp;utm_medium=qr&amp;utm_campaign=ho-so-nang-luc', false)
            ->assertSee('data:image/png;base64,', false)
            ->assertSee('ma-qr-ho-so-nang-luc.svg', false)
            ->fillForm(['trang' => 'https://facebook.com/psv'])
            ->assertSee('Trang đích phải là một trang trên psvtravel.com');

        // Nhân viên bán hàng thường, khách hàng: không vào được
        $this->actingAs(User::factory()->create());
        $this->assertFalse(TaoLinkChienDich::canAccess());
    }
}
