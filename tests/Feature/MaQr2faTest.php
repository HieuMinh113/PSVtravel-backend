<?php

namespace Tests\Feature;

use App\Filament\Auth\XacThucApp;
use App\Models\User;
use Filament\Facades\Filament;
use Tests\TestCase;

/**
 * Mã QR thiết lập 2FA phải là data URI ĐÚNG MỘT LỚP base64 chứa SVG.
 *
 * pragmarx/google2fa-qrcode 4.x tự trả "data:image/svg+xml;base64,..." còn
 * Filament lại bọc thêm lần nữa → ảnh QR hỏng trên máy không có imagick
 * (đã xảy ra thật trên VPS). Test này chặn lỗi quay lại khi cập nhật thư viện.
 */
class MaQr2faTest extends TestCase
{
    public function test_ma_qr_la_svg_hop_le_khong_bi_boc_hai_lop(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::auth()->setUser(new User(['email' => 'admin@psvtravel.com']));

        $uri = XacThucApp::make()->brandName('PSV Travel')->generateQrCodeDataUri('MXRJHAP6QESODU5M');

        $this->assertStringStartsWith('data:image/', $uri);
        $noiDung = base64_decode(substr($uri, strpos($uri, ',') + 1), true);
        $this->assertNotFalse($noiDung);
        $this->assertStringNotContainsString('data:image', $noiDung, 'Mã QR bị bọc base64 hai lớp');
        $this->assertTrue(str_contains($noiDung, '<svg') || str_starts_with($noiDung, "\x89PNG"), 'Nội dung phải là ảnh SVG/PNG');
    }

    public function test_panel_dung_lop_sua_loi(): void
    {
        $nhaCungCap = Filament::getPanel('admin')->getMultiFactorAuthenticationProviders();
        $this->assertInstanceOf(XacThucApp::class, reset($nhaCungCap));
    }
}
