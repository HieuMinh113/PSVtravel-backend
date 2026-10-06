<?php

namespace App\Filament\Auth;

use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Facades\Filament;
use SensitiveParameter;

/**
 * 2FA bằng app Authenticator — sửa lỗi MÃ QR KHÔNG HIỆN của Filament.
 *
 * pragmarx/google2fa-qrcode (bản 4.x) đã tự trả về chuỗi hoàn chỉnh
 * "data:image/svg+xml;base64,...", nhưng Filament tưởng nhận SVG thô nên khi
 * máy chủ không có extension imagick (đúng như VPS) lại bọc base64 thêm lần
 * nữa → ảnh bị mã hoá hai lớp, trình duyệt không đọc được, chỉ hiện dòng chữ
 * thay thế. Ở đây chỉ bọc khi thư viện CHƯA tự bọc — đúng cả khi có/không có
 * imagick, và vẫn đúng nếu sau này Filament tự sửa.
 */
class XacThucApp extends AppAuthentication
{
    public function generateQrCodeDataUri(#[SensitiveParameter] string $secret): string
    {
        /** @var HasAppAuthentication $user */
        $user = Filament::auth()->user();

        $qr = $this->google2FA->getQRCodeInline(
            $this->getBrandName(),
            $this->getHolderName($user),
            $secret,
        );

        if (str_starts_with($qr, 'data:')) {
            return $qr;
        }

        // Bản thư viện cũ trả SVG thô → tự bọc thành data URI
        return 'data:image/svg+xml;base64,'.base64_encode($qr);
    }
}
