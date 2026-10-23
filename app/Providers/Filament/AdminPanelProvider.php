<?php

namespace App\Providers\Filament;

use App\Filament\Auth\XacThucApp;
use App\Filament\Pages\HuongDan;
use App\Filament\Pages\TongQuan;
use App\Filament\Widgets\DonMoiNhatWidget;
use App\Filament\Widgets\LoiTatWidget;
use App\Filament\Widgets\TongQuanWidget;
use App\Filament\Widgets\ViecCanLamWidget;
use App\Http\Controllers\Admin\DocLinkBanDoController;
use App\Http\Controllers\Admin\NhipThongBaoController;
use App\Http\Controllers\Admin\TaiMaQrTourController;
use App\Http\Controllers\Admin\TaiTepUngTuyenController;
use App\Http\Middleware\BatBuoc2faQuanTri;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Actions\Action;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
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
        // Tên mục viết hoa chữ đầu như câu thường ("Khoảnh khắc du khách"), không
        // viết hoa từng chữ — Filament mặc định viết hoa mọi chữ của nhãn model
        Resource::titleCaseModelLabel(false);

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // Ghi ten thuong hieu thang vao day thay vi de Filament lay
            // config('app.name'). Neu .env tren may nao do con APP_NAME=Laravel
            // thi trang quan tri van hien dung "PSV Travel".
            ->brandName('PSV Travel')
            ->brandLogo(asset('images/psv-logo.png'))
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('favicon.ico'))
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
            // Xanh PSV (giống website) cho nút, mục đang chọn. Vàng chỉ còn dùng cho
            // "chờ xử lý", đỏ cho lỗi / quá hạn — màu mang nghĩa, không trang trí
            ->colors([
                'primary' => Color::hex('#0169A9'),
                'gray' => Color::Slate,
            ])
            // Cùng phông với website, dấu tiếng Việt rõ hơn Inter mặc định. Tệp
            // phông lưu ngay trong public/fonts (không tải từ dịch vụ ngoài)
            ->font('Be Vietnam Pro', url: asset('fonts/be-vietnam-pro/be-vietnam-pro.css'), provider: LocalFontProvider::class)
            ->maxContentWidth(Width::ScreenTwoExtraLarge)
            ->sidebarCollapsibleOnDesktop()
            // Thứ tự nhóm theo việc làm hằng ngày. Nhóm ít dùng thu gọn sẵn (bấm
            // để mở, trình duyệt nhớ lựa chọn). Nhóm không có mục nào người xem
            // được phép mở thì tự ẩn.
            ->navigationGroups([
                NavigationGroup::make('Bán hàng'),
                NavigationGroup::make('Tour & điều hành'),
                NavigationGroup::make('Visa'),
                NavigationGroup::make('Marketing'),
                NavigationGroup::make('Nội dung website')->collapsed(),
                NavigationGroup::make('Giới thiệu công ty')->collapsed(),
                NavigationGroup::make('Nhân sự'),
                NavigationGroup::make('Hệ thống')->collapsed(),
            ])
            ->userMenuItems([
                Action::make('huongDan')
                    ->label('Hướng dẫn sử dụng')
                    ->icon(Heroicon::OutlinedQuestionMarkCircle)
                    ->url(fn (): string => HuongDan::getUrl()),
            ])
            // Chuông thông báo góc phải: báo động liên minh (có chỗ trở lại,
            // sắp hết chỗ, sheet lỗi...). Hỏi lại mỗi 30 giây.
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                TongQuan::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                TongQuanWidget::class,
                ViecCanLamWidget::class,
                LoiTatWidget::class,
                DonMoiNhatWidget::class,
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
                FilamentShieldPlugin::make()
                    ->navigationGroup('Hệ thống')
                    ->navigationSort(20)
                    ->navigationLabel('Vai trò')
                    ->modelLabel('vai trò')
                    ->pluralModelLabel('Vai trò'),
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
                // Ô chọn vị trí (Bản đồ cẩm nang): đọc toạ độ từ link Google
                // Maps rút gọn — xem public/js/psv/chon-vi-tri.js
                Route::get('psv/doc-link-ban-do', DocLinkBanDoController::class)
                    ->middleware([BatBuoc2faQuanTri::class, 'throttle:30,1'])
                    ->name('psv.doc-link-ban-do');
                // Mở CV / portfolio của hồ sơ ứng tuyển (kiểm tra quyền mỗi lần mở)
                Route::get('ung-tuyen/{hoSo}/{loai}', TaiTepUngTuyenController::class)
                    ->whereIn('loai', ['cv', 'portfolio'])
                    ->middleware(BatBuoc2faQuanTri::class)
                    ->name('ung-tuyen.tep');
            })
            ->renderHook(PanelsRenderHook::BODY_END, fn () => auth()->check() ? view('filament.thong-bao.nhip') : '')
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn () => view('filament.thong-bao.nut-tieng'))
            // Nút "Hướng dẫn" luôn thấy trên thanh trên cùng
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn () => auth()->check() ? view('filament.nut-huong-dan') : '')
            // Tinh chỉnh nhỏ không cần dựng lại CSS: chữ số đều cột, logo nền tối
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn () => view('filament.giao-dien'));
    }
}
