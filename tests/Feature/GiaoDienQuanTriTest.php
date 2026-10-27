<?php

namespace Tests\Feature;

use App\Filament\Pages\HuongDan;
use App\Filament\Widgets\LoiTatWidget;
use App\Filament\Widgets\ViecCanLamWidget;
use App\Models\ContactMessage;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Trang quản trị sau khi xếp lại: menu đúng nhóm, đúng thứ tự, tên mục viết
 * thường; hướng dẫn chỉ tới đúng menu đang có; Tổng quan theo vai trò.
 */
class GiaoDienQuanTriTest extends TestCase
{
    use RefreshDatabase;

    private const NHOM = ['Bán hàng', 'Tour & điều hành', 'Maldives', 'Visa', 'Marketing', 'Nội dung website', 'Giới thiệu công ty', 'Nhân sự', 'Hệ thống'];

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    /** @return array<string, ?string> tên mục menu → nhóm */
    private function menu(): array
    {
        $panel = Filament::getPanel('admin');
        $ds = [];
        foreach ([...$panel->getResources(), ...$panel->getPages()] as $lop) {
            if (! $lop::shouldRegisterNavigation()) {
                continue;
            }
            $nhom = $lop::getNavigationGroup();
            $ds[$lop::getNavigationLabel()] = $nhom instanceof \UnitEnum ? $nhom->name : $nhom;
        }

        return $ds;
    }

    public function test_menu_xep_theo_nhom_cong_viec_va_ten_viet_thuong(): void
    {
        $nhomPanel = array_map(fn ($g) => is_string($g) ? $g : $g->getLabel(), array_values(Filament::getPanel('admin')->getNavigationGroups()));
        $this->assertSame(self::NHOM, $nhomPanel);

        $menu = $this->menu();
        foreach ($menu as $ten => $nhom) {
            $this->assertTrue($nhom === null || in_array($nhom, self::NHOM, true), "{$ten} nằm trong nhóm lạ: {$nhom}");
            // Không viết hoa từng chữ ("Khoảnh Khắc Du Khách")
            $tu = preg_split('/\s+/u', $ten);
            $hoa = count(array_filter($tu, fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)) === mb_substr($w, 0, 1) && preg_match('/^\p{L}/u', $w)));
            $this->assertTrue(count($tu) < 3 || $hoa < count($tu), "Tên mục viết hoa từng chữ: {$ten}");
        }

        // Việc hằng ngày ở nhóm đầu; nội dung website không lẫn vào bán hàng
        $this->assertNull($menu['Chấm công']);
        $this->assertSame('Bán hàng', $menu['Đơn đặt tour']);
        $this->assertSame('Bán hàng', $menu['Tin liên hệ']);
        $this->assertSame('Marketing', $menu['Tạo link chiến dịch']);
        $this->assertSame('Marketing', $menu['Chuyển hướng link cũ']);
        $this->assertSame('Nhân sự', $menu['Tuyển dụng']);
        $this->assertSame('Nội dung website', $menu['Khoảnh khắc du khách']);
        $this->assertSame('Hệ thống', $menu['Người dùng']);
        $this->assertSame('Maldives', $menu['Đơn Maldives']);
        $this->assertSame('Maldives', $menu['Combo Maldives']);
        $this->assertArrayNotHasKey('Hãng bay', $menu); // thay bằng Maldives, chỉ ẩn khỏi menu
        $this->assertArrayNotHasKey('Hướng dẫn sử dụng', $menu); // mở bằng nút trên thanh trên cùng
    }

    public function test_huong_dan_chi_toi_dung_menu_dang_co(): void
    {
        $menu = $this->menu();
        $kiem = 0;
        foreach (HuongDan::noiDung() as $tab) {
            foreach ($tab['muc'] as $m) {
                $o = $m['o_dau'] ?? '';
                // "Nhóm → Mục" hoặc "Nhóm → Mục A / Mục B" (bỏ phần trong ngoặc, đoạn chỉ nút sau mục)
                $phan = explode(' → ', preg_replace('/\s*\(.*\)$/u', '', $o));
                if (count($phan) < 2 || ! in_array($phan[0], self::NHOM, true)) {
                    continue;
                }
                foreach (explode(' / ', $phan[1]) as $muc) {
                    $this->assertArrayHasKey($muc, $menu, "Hướng dẫn nhắc \"{$o}\" nhưng menu không có mục \"{$muc}\"");
                    $this->assertSame($phan[0], $menu[$muc], "\"{$muc}\" không nằm trong nhóm {$phan[0]}");
                    $kiem++;
                }
            }
        }
        $this->assertGreaterThan(20, $kiem);
    }

    public function test_huong_dan_mo_dung_tab_theo_vai_tro_va_tong_quan_theo_quyen(): void
    {
        $this->seed(RoleSeeder::class);
        $sale = User::factory()->create()->assignRole('sale');
        $mk = User::factory()->create()->assignRole('marketing');

        $this->actingAs($sale);
        Livewire::test(HuongDan::class)->assertOk()
            ->assertSee('tab: \'sale\'', false)
            ->assertSee('Nhận đơn khách đặt trên website')
            ->assertSee('Duyệt khoản thu'); // các tab khác vẫn đọc được

        // Sale: tạo đơn được, không thêm tour, không tạo link chiến dịch
        Livewire::test(LoiTatWidget::class)->assertSee('Tạo đơn đặt tour')->assertDontSee('Thêm tour')
            ->assertDontSee('Tạo link chiến dịch')->assertSee('Hướng dẫn sử dụng');

        // Việc cần làm dùng đúng số trên menu
        ContactMessage::create(['name' => 'A', 'phone' => '0900000000', 'message' => 'Hỏi tour Tết', 'source' => 'contact']);
        $this->actingAs(User::factory()->create()->assignRole('super_admin'));
        Livewire::test(ViecCanLamWidget::class)->assertSee('Tin liên hệ')->assertSee('Chưa ai xử lý');

        $this->actingAs($mk);
        Livewire::test(HuongDan::class)->assertSee('tab: \'marketing\'', false);
        Livewire::test(LoiTatWidget::class)->assertSee('Thêm tour')->assertSee('Tạo link chiến dịch');
        // Marketing không mở được Tin liên hệ → không thấy việc đó
        Livewire::test(ViecCanLamWidget::class)->assertDontSee('Chưa ai xử lý');
        Livewire::test(HuongDan::class, ['tab' => 'visa'])->assertOk();
    }
}
