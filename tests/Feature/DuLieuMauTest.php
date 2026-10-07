<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Payment;
use Modules\Tour\Models\Tour;
use Modules\Tour\Models\TourDeparture;
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

        $this->assertSame(14, Tour::published()->count());
        $this->assertSame(7, Tour::where('type', 'domestic')->count());
        $this->assertTrue(TourDeparture::where('status', 'full')->exists());
        $this->get('/api/v1/tours?category=dong-bac-a')->assertOk()->assertJsonCount(2, 'data');
        $this->get('/api/v1/destinations/seoul')->assertOk();

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
        $this->assertGreaterThanOrEqual(1, $admin->notifications()->count()); // hồ sơ visa web
        $this->assertSame(1, User::where('email', 'sale@psvtravel.com')->sole()->notifications()->count()); // tới hạn nhắn khách

        // Kế toán + khoản thu chờ duyệt có ảnh chuyển khoản; trang thống kê mở được
        $this->assertTrue(User::where('email', 'ketoan@psvtravel.com')->firstOrFail()->hasRole('ke_toan'));
        $choDuyet = Payment::where('status', 'pending')->whereNotNull('received_by')->sole();
        Storage::disk('rieng')->assertExists($choDuyet->proof_images[0]);
        $this->assertSame(User::where('email', 'sale@psvtravel.com')->value('id'), $toiHan->assigned_to);
        $this->actingAs(User::where('email', 'ketoan@psvtravel.com')->sole())->get('/admin/thong-ke-doanh-so')->assertOk()->assertSee('Kinh doanh — Thu Trang');
        $this->get('/admin/khoan-thu')->assertOk();

        $this->artisan('psv:don-du-lieu-mau', ['--don-hang' => true, '--force' => true])->assertSuccessful();
        $this->assertSame(0, VisaCase::withTrashed()->count());
        $tep->each(fn ($d) => Storage::disk('rieng')->assertMissing($d));
        Storage::disk('rieng')->assertMissing($choDuyet->proof_images[0]);
    }
}
