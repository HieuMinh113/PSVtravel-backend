<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Page\Models\Setting;
use Tests\TestCase;

/**
 * Migration sửa thông tin công ty: cơ quan cấp phép lữ hành phải là Cục Du
 * lịch Quốc gia Việt Nam (trước đây nhập nhầm "BỘ CÔNG AN"), email chính thức.
 */
class ThongTinCongTyTest extends TestCase
{
    use RefreshDatabase;

    private function chayMigration(): void
    {
        $m = require base_path('Modules/Page/database/migrations/2026_10_06_100000_sua_co_quan_cap_phep_va_email.php');
        $m->up();
    }

    public function test_sua_gia_tri_sai_va_xoa_bo_nho_dem(): void
    {
        Setting::query()->where('key', 'license_issuer')->delete();
        Setting::create(['key' => 'license_issuer', 'value' => 'BỘ CÔNG AN', 'label' => 'Cơ quan cấp giấy phép lữ hành', 'group' => 'legal']);
        Setting::query()->updateOrCreate(['key' => 'email'], ['value' => 'hi@psvtravel.com', 'label' => 'Email liên hệ', 'group' => 'contact']);
        $this->assertSame('BỘ CÔNG AN', Setting::lay('license_issuer')); // nạp vào bộ nhớ đệm

        $this->chayMigration();

        $this->assertSame('Cục Du lịch Quốc gia Việt Nam', Setting::lay('license_issuer'));
        $this->assertSame('nguyendusit399@gmail.com', Setting::lay('email'));
        $this->assertSame(1, Setting::query()->where('key', 'license_issuer')->count());
    }

    public function test_chua_co_o_thi_tao_moi_co_nhan(): void
    {
        Setting::query()->whereIn('key', ['license_issuer', 'email'])->delete();
        Cache::forget('psv_settings');

        $this->chayMigration();

        $o = Setting::query()->where('key', 'license_issuer')->first();
        $this->assertSame('Cục Du lịch Quốc gia Việt Nam', $o->value);
        $this->assertSame('Cơ quan cấp giấy phép lữ hành', $o->label);
        $this->assertSame('nguyendusit399@gmail.com', Setting::lay('email'));
    }

    public function test_api_cai_dat_tra_gia_tri_moi(): void
    {
        $this->chayMigration();

        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.license_issuer', 'Cục Du lịch Quốc gia Việt Nam')
            ->assertJsonPath('data.email', 'nguyendusit399@gmail.com');
    }
}
