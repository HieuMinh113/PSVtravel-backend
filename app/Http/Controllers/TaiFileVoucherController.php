<?php

namespace App\Http\Controllers;

use App\Services\Voucher\XuatVoucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Gửi file in / ZIP ảnh voucher vừa xuất rồi xoá ngay (không đẩy file qua
 * Livewire — file vài MB sẽ bị mã hoá base64 trong phản hồi JSON).
 */
class TaiFileVoucherController extends Controller
{
    public function __invoke(Request $request, string $ma): BinaryFileResponse
    {
        // Link có chữ ký kèm id người bấm xuất — người khác có link cũng không tải được
        abort_unless(auth()->check() && (int) $request->query('u') === (int) auth()->id(), 403);

        $duong = XuatVoucher::duong($ma);
        abort_unless(Storage::disk('rieng')->exists($duong), 404, 'File đã được tải hoặc đã hết hạn — bấm xuất lại.');

        $ten = basename((string) $request->query('ten', 'voucher.pdf'));
        $loai = match (strtolower(pathinfo($ten, PATHINFO_EXTENSION))) {
            'zip' => 'application/zip',
            'jpg' => 'image/jpeg',
            default => 'application/pdf',
        };

        return response()->download(Storage::disk('rieng')->path($duong), $ten, ['Content-Type' => $loai])
            ->deleteFileAfterSend();
    }
}
