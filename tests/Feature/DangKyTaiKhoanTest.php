<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

/**
 * Đăng ký trên website: không được lỗi 500 khi vai trò "customer" bị xoá nhầm
 * hay máy chủ mail trục trặc, và không để lại tài khoản dở dang.
 */
class DangKyTaiKhoanTest extends TestCase
{
    use RefreshDatabase;

    private array $form = [
        'name' => 'Lê Minh Hiếu', 'username' => 'hieuminh', 'email' => 'hieuminh.thu@gmail.com',
        'phone' => '0876143711', 'password' => 'MatKhau2026x', 'password_confirmation' => 'MatKhau2026x',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(); // kiểm tra mật khẩu rò rỉ — không gọi mạng thật
    }

    public function test_dang_ky_duoc_ca_khi_vai_tro_customer_bi_xoa(): void
    {
        Mail::fake();
        Role::where('name', 'customer')->delete();

        $this->postJson('/api/v1/auth/register', $this->form)->assertCreated();

        $u = User::where('email', 'hieuminh.thu@gmail.com')->sole();
        $this->assertTrue($u->hasRole('customer'));
        Mail::assertSent(OtpMail::class, fn (OtpMail $m) => $m->hasTo('hieuminh.thu@gmail.com'));
    }

    public function test_khong_gui_duoc_mail_thi_bao_ro_va_khong_tao_tai_khoan_do_dang(): void
    {
        Mail::shouldReceive('to')->andThrow(new TransportException('Connection refused'));

        $this->postJson('/api/v1/auth/register', $this->form)
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Hệ thống chưa gửi được email mã xác thực. Vui lòng thử lại sau ít phút hoặc gọi hotline để được hỗ trợ.');
        $this->assertSame(0, User::where('email', 'hieuminh.thu@gmail.com')->count());
        $this->assertSame(0, OtpCode::count());

        // Quên mật khẩu cũng vậy, và không bị tính vào giới hạn số lần gửi
        User::factory()->create(['email' => 'cu@gmail.com']);
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'cu@gmail.com'])->assertStatus(422);
        $this->assertSame(0, OtpCode::count());
    }

    public function test_migration_gan_lai_vai_tro_cho_tai_khoan_dang_ky_do_dang(): void
    {
        Role::where('name', 'customer')->delete();
        $doDang = User::factory()->create(['email_verified_at' => null]);
        $nhanVienChuaGan = User::factory()->create(); // admin tạo, đã xác thực
        $sale = User::factory()->create(['email_verified_at' => null])->assignRole(Role::findOrCreate('sale', 'web'));

        (require database_path('migrations/2026_10_18_090000_khoi_phuc_vai_tro_khach_hang.php'))->up();

        $this->assertTrue($doDang->fresh()->hasRole('customer'));
        $this->assertFalse($nhanVienChuaGan->fresh()->hasRole('customer'));
        $this->assertSame(['sale'], $sale->fresh()->getRoleNames()->all());
    }
}
