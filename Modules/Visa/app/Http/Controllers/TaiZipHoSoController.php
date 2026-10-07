<?php

namespace Modules\Visa\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Visa\Services\XuatTam;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Gửi file ZIP hồ sơ visa vừa xuất cho trình duyệt rồi xoá ngay.
 *
 * Không đẩy file qua Livewire: Livewire mã hoá base64 cả file vào phản hồi
 * JSON — ZIP cả đoàn vài chục / vài trăm MB sẽ làm treo trình duyệt và tốn RAM
 * máy chủ. Trang quản trị tạo ZIP, cất tạm, rồi chuyển hướng sang đây.
 */
class TaiZipHoSoController extends Controller
{
    public function __invoke(Request $request, string $ma): BinaryFileResponse
    {
        // Link có chữ ký kèm id người bấm xuất — người khác có link cũng không tải được
        abort_unless(auth()->check() && (int) $request->query('u') === (int) auth()->id(), 403);

        $duong = XuatTam::duong($ma);
        abort_unless(Storage::disk('rieng')->exists($duong), 404, 'File đã được tải hoặc đã hết hạn — bấm xuất lại.');

        $ten = (string) $request->query('ten', 'ho-so-visa.zip');

        return response()->download(Storage::disk('rieng')->path($duong), $ten, ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }
}
