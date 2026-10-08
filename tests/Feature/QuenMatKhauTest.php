<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Quên mật khẩu trên website: gửi mã 6 số qua email → nhập mã + mật khẩu mới
 * → đăng nhập luôn, các phiên cũ bị thu hồi. Không tiết lộ email có tài khoản.
 */
class QuenMatKhauTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::fake(); // kiểm tra mật khẩu rò rỉ (haveibeenpwned) — không gọi mạng thật
    }

    private function maVuaGui(string $email): string
    {
        $ma = null;
        Mail::assertSent(OtpMail::class, function (OtpMail $m) use ($email, &$ma) {
            if ($m->hasTo($email)) {
                $ma = $m->ma;
            }

            return $m->hasTo($email);
        });

        return $ma;
    }

    public function test_quen_mat_khau_gui_ma_va_dat_lai(): void
    {
        $u = User::factory()->create(['email' => 'khach@example.com', 'password' => Hash::make('MatKhauCu1')]);
        $u->createToken('may-cu');

        // Email không có tài khoản → cùng câu trả lời, không gửi mail
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'khong-co@example.com'])
            ->assertOk()->assertJsonPath('data.email', 'khong-co@example.com');
        Mail::assertNothingSent();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'KHACH@example.com '])->assertOk();
        $ma = $this->maVuaGui('khach@example.com');
        Mail::assertSent(OtpMail::class, fn (OtpMail $m) => $m->quenMatKhau()
            && str_contains($m->envelope()->subject, 'Mã đặt lại mật khẩu')
            && str_contains($m->render(), 'đặt lại mật khẩu'));

        // Xin lại ngay → phải chờ 60 giây
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'khach@example.com'])
            ->assertStatus(422)->assertJsonValidationErrors('email');

        // Sai mã / mật khẩu nhập lại không khớp
        $sai = $ma === '000000' ? '111111' : '000000';
        $this->postJson('/api/v1/auth/reset-password', ['email' => 'khach@example.com', 'code' => $sai, 'password' => 'MatKhauMoi9', 'password_confirmation' => 'MatKhauMoi9'])
            ->assertStatus(422)->assertJsonPath('errors.code.0', 'Mã xác thực không đúng. Bạn còn 4 lần thử.');
        $this->postJson('/api/v1/auth/reset-password', ['email' => 'khach@example.com', 'code' => $ma, 'password' => 'MatKhauMoi9', 'password_confirmation' => 'khac'])
            ->assertStatus(422)->assertJsonValidationErrors('password');

        // Đúng mã → đổi mật khẩu, trả token, phiên cũ bị thu hồi
        $tl = $this->postJson('/api/v1/auth/reset-password', ['email' => 'khach@example.com', 'code' => $ma, 'password' => 'MatKhauMoi9', 'password_confirmation' => 'MatKhauMoi9'])
            ->assertOk();
        $this->assertNotEmpty($tl->json('data.token'));
        $u->refresh();
        $this->assertTrue(Hash::check('MatKhauMoi9', $u->password));
        $this->assertSame(1, $u->tokens()->count()); // chỉ còn token vừa cấp

        // Mã đã dùng không dùng lại được
        $this->postJson('/api/v1/auth/reset-password', ['email' => 'khach@example.com', 'code' => $ma, 'password' => 'MatKhauKhac8', 'password_confirmation' => 'MatKhauKhac8'])
            ->assertStatus(422);

        // Đăng nhập bằng mật khẩu mới
        $this->postJson('/api/v1/auth/login', ['login' => 'khach@example.com', 'password' => 'MatKhauMoi9'])->assertOk();
    }

    public function test_ma_dang_ky_khong_dung_de_dat_lai_mat_khau_va_email_la_khong_lo(): void
    {
        $u = User::factory()->create(['email' => 'moi@example.com', 'email_verified_at' => null]);
        $this->postJson('/api/v1/auth/resend-otp', ['email' => 'moi@example.com'])->assertOk();
        $maDangKy = $this->maVuaGui('moi@example.com');

        $this->postJson('/api/v1/auth/reset-password', ['email' => 'moi@example.com', 'code' => $maDangKy, 'password' => 'MatKhauMoi9', 'password_confirmation' => 'MatKhauMoi9'])
            ->assertStatus(422);

        // Email lạ → cùng câu báo như mã sai
        $this->postJson('/api/v1/auth/reset-password', ['email' => 'la@example.com', 'code' => '123456', 'password' => 'MatKhauMoi9', 'password_confirmation' => 'MatKhauMoi9'])
            ->assertStatus(422)->assertJsonPath('errors.code.0', 'Mã xác thực không tồn tại hoặc đã hết hạn. Vui lòng bấm gửi lại mã.');

        // Tài khoản chưa xác thực email: đặt lại mật khẩu bằng mã qua email = xác thực luôn
        $this->travelTo(Carbon::now()->addMinutes(2));
        Mail::fake();
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'moi@example.com'])->assertOk();
        $ma = $this->maVuaGui('moi@example.com');
        $this->postJson('/api/v1/auth/reset-password', ['email' => 'moi@example.com', 'code' => $ma, 'password' => 'MatKhauMoi9', 'password_confirmation' => 'MatKhauMoi9'])
            ->assertOk();
        $this->assertNotNull($u->fresh()->email_verified_at);
    }
}
