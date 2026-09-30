<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Đường thoát khẩn cấp: tắt 2FA của một tài khoản khi người đó mất điện thoại
 * VÀ mất luôn mã khôi phục — nhất là khi đó lại là super admin duy nhất, không
 * còn ai vào trang quản trị để bấm "Tắt 2FA" giúp.
 *
 *   docker compose -f docker-compose.prod.yml exec app php artisan psv:tat-2fa email@congty.vn
 *
 * Tài khoản admin sẽ bị yêu cầu thiết lập lại 2FA ngay lần đăng nhập kế tiếp.
 */
class Tat2fa extends Command
{
    protected $signature = 'psv:tat-2fa {email : Email tài khoản cần tắt 2FA}';

    protected $description = 'Tắt xác thực 2 lớp (2FA) trang quản trị của một tài khoản (khi mất điện thoại)';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('Không tìm thấy tài khoản với email này.');

            return self::FAILURE;
        }

        $user->saveAppAuthenticationSecret(null);
        $user->saveAppAuthenticationRecoveryCodes(null);
        $user->save();

        activity('user')->performedOn($user)->log('Tắt 2FA bằng lệnh máy chủ (psv:tat-2fa)');

        $this->info("Đã tắt 2FA cho {$user->email}. Lần đăng nhập tới sẽ được yêu cầu thiết lập lại (nếu là admin).");

        return self::SUCCESS;
    }
}
