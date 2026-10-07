<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Booking\Models\Booking;
use Modules\Visa\Models\VisaCase;
use Tests\TestCase;

/**
 * Dữ liệu mẫu cho máy local: tài khoản theo vai trò, đơn tour tới hạn nhắn
 * khách, hồ sơ visa đủ trạng thái có file — và lệnh dọn xoá sạch cả file.
 */
class DuLieuMauTest extends TestCase
{
    use RefreshDatabase;

    public function test_tao_du_lieu_mau_va_don_sach(): void
    {
        Storage::fake('rieng');
        $this->seed(RoleSeeder::class);
        $this->seed(DemoSeeder::class);

        $visa1 = User::where('email', 'visa1@psvtravel.com')->firstOrFail();
        $this->assertTrue($visa1->hasRole('visa'));
        $this->assertTrue(User::where('email', 'khach@example.com')->firstOrFail()->hasRole('customer'));

        $this->assertSame(8, VisaCase::count());
        $web = VisaCase::where('source', 'website')->sole();
        $this->assertTrue($web->chuaAiNhan());
        // visa1 thấy hồ sơ của mình + hồ sơ chưa ai nhận, không thấy đoàn của visa2
        $this->assertSame(5, VisaCase::nhinThayBoi($visa1)->count());
        $tep = VisaCase::all()->flatMap->tatCaFile();
        $this->assertNotEmpty($tep);
        $tep->each(fn ($d) => Storage::disk('rieng')->assertExists($d));

        $toiHan = Booking::where('customer_email', 'khach3@example.com')->sole();
        $this->assertTrue($toiHan->canNhacKhach());
        $admin = User::where('email', 'admin@psvtravel.com')->firstOrFail();
        $this->assertGreaterThanOrEqual(2, $admin->notifications()->count()); // nhắc khách + hồ sơ web

        $this->artisan('psv:don-du-lieu-mau', ['--don-hang' => true, '--force' => true])->assertSuccessful();
        $this->assertSame(0, VisaCase::withTrashed()->count());
        $tep->each(fn ($d) => Storage::disk('rieng')->assertMissing($d));
    }
}
