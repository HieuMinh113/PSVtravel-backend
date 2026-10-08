<?php

namespace App\Providers\Filament;

use App\Filament\Auth\XacThucApp;
use App\Filament\Widgets\DonMoiNhatWidget;
use App\Filament\Widgets\TongQuanWidget;
use App\Http\Controllers\Admin\NhipThongBaoController;
use App\Http\Controllers\Admin\TaiMaQrTourController;
use App\Http\Middleware\BatBuoc2faQuanTri;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // Ghi ten thuong hieu thang vao day thay vi de Filament lay
            // config('app.name'). Neu .env tren may nao do con APP_NAME=Laravel
            // thi trang quan tri van hien dung "PSV Travel".
            ->brandName('PSV Travel')
            ->login()
            // Trang Hồ sơ (menu người dùng góc phải): nơi bật / tắt 2FA, xem lại
            // mã khôi phục, đổi mật khẩu.
            ->profile()
            // Xác thực 2 lớp bằng app Google Authenticator (mã 6 số đổi mỗi 30s).
            // recoverable(): cấp mã khôi phục dùng khi mất điện thoại.
            //
            // isRequired: true chỉ để Filament dựng sẵn bước "bắt buộc thiết lập
            // 2FA"; AI bị bắt buộc do BatBuoc2faQuanTri quyết định — super_admin
            // và admin bắt buộc, nhân viên khác tuỳ chọn.
            ->multiFactorAuthentication(
                [
                    XacThucApp::make()
                        ->brandName('PSV Travel')
                        ->recoverable(),
                ],
                isRequired: true,
            )
            ->multiFactorAuthenticationRequiredMiddlewareName(BatBuoc2faQuanTri::class)
            ->colors([
                'primary' => Color::Amber,
            ])
            // Chuông thông báo góc phải: báo động liên minh (có chỗ trở lại,
            // sắp hết chỗ, sheet lỗi...). Hỏi lại mỗi 30 giây.
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                TongQuanWidget::class,
                DonMoiNhatWidget::class,
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            // Tải mã QR tour (PNG/SVG). Route tự viết KHÔNG được Filament gắn
            // sẵn lớp bắt buộc 2FA như các trang, nên thêm tay.
            ->authenticatedRoutes(function (): void {
                Route::get('tours/{tour}/ma-qr.{dinhDang}', TaiMaQrTourController::class)
                    ->whereIn('dinhDang', ['png', 'svg'])
                    ->middleware(BatBuoc2faQuanTri::class)
                    ->name('tours.ma-qr');
                // Nhịp thông báo: trình duyệt hỏi mỗi 10 giây (tiếng báo, popup,
                // số trên menu) — xem public/js/psv/thong-bao.js
                Route::get('psv/nhip', NhipThongBaoController::class)
                    ->middleware(BatBuoc2faQuanTri::class)
                    ->name('psv.nhip');
            })
            ->renderHook(PanelsRenderHook::BODY_END, fn () => auth()->check() ? view('filament.thong-bao.nhip') : '')
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn () => view('filament.thong-bao.nut-tieng'));
    }
}
