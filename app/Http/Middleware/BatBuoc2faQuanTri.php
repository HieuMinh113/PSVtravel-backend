<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Auth\MultiFactor\Http\Middleware\EnsureMultiFactorAuthenticationIsEnabled;
use Filament\Facades\Filament;
use Illuminate\Http\Request;

/**
 * Bắt buộc 2FA với super_admin / admin; nhân viên khác thì TUỲ CHỌN.
 *
 * Filament chỉ có một công tắc "bắt buộc 2FA" cho cả panel, quyết định một lần
 * lúc khởi động (chưa biết ai đăng nhập). Nên bật công tắc đó, rồi thay
 * middleware mặc định bằng lớp này: chỉ chuyển sang bước "thiết lập 2FA" khi
 * người đang đăng nhập là quản trị viên; nhân viên thường đi tiếp bình thường.
 */
class BatBuoc2faQuanTri
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = Filament::auth()->user();

        if (! $user || ! method_exists($user, 'phaiBat2fa') || ! $user->phaiBat2fa()) {
            return $next($request);
        }

        return app(EnsureMultiFactorAuthenticationIsEnabled::class)->handle($request, $next);
    }
}
