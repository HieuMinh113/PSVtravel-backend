<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceFace;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\EventSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Payment;
use Modules\Tour\Models\Tour;
use Modules\Tour\Models\TourDeparture;
use Modules\Visa\Models\VisaCase;
use Modules\Visa\Models\VisaChecklist;
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

        // Chấm công mẫu: 3 nhân viên × 10 ngày, có trễ chờ duyệt, nghi vấn, quên chấm ra
        $this->assertSame(3, AttendanceFace::count());
        $this->assertSame(30, Attendance::count());
        $this->assertTrue(Attendance::where('review_status', 'cho_duyet')->exists());
        $this->assertTrue(Attendance::all()->contains(fn ($a) => $a->nghiVan()));
        $this->assertTrue(Attendance::all()->contains(fn ($a) => $a->late_minutes > 0));

        $this->seed(EventSeeder::class);
        $suKienThat = Event::create(['title' => 'Gói thật', 'slug' => 'goi-that', 'status' => 'published']);
        $soMauChecklist = VisaChecklist::count();
        $this->assertGreaterThan(40, $soMauChecklist);
        $anhChamCong = Attendance::whereNotNull('in_photo')->value('in_photo');
        $anhMat = AttendanceFace::value('photo');
        Storage::disk('rieng')->assertExists($anhChamCong);

        // Xem trước: không xoá gì
        $this->artisan('psv:don-du-lieu-mau', ['--don-hang' => true, '--tai-khoan-mau' => true, '--xem' => true])
            ->expectsOutputToContain('chưa xoá gì')->assertSuccessful();
        $this->assertSame(30, Attendance::count());

        // Xoá hết, chỉ giữ cấu hình + mẫu checklist visa + tài khoản quản trị
        $this->artisan('psv:don-du-lieu-mau', ['--don-hang' => true, '--tai-khoan-mau' => true, '--force' => true])->assertSuccessful();
        $this->assertSame(0, VisaCase::withTrashed()->count());
        $tep->each(fn ($d) => Storage::disk('rieng')->assertMissing($d));
        Storage::disk('rieng')->assertMissing($choDuyet->proof_images[0]);
        $this->assertSame(0, Attendance::count());
        $this->assertSame(0, AttendanceFace::count());
        Storage::disk('rieng')->assertMissing($anhChamCong);
        Storage::disk('rieng')->assertMissing($anhMat);
        $this->assertSame(0, Tour::count());
        $this->assertSame(0, Booking::count());
        $this->assertSame($soMauChecklist, VisaChecklist::count()); // giữ mẫu checklist
        $this->assertSame(['goi-that'], Event::pluck('slug')->all());       // chỉ xoá gói mẫu
        $this->assertSame(['admin@psvtravel.com'], User::orderBy('email')->pluck('email')->all());
        $this->assertTrue($suKienThat->exists);
    }
}
