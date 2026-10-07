<?php

namespace Modules\Booking\Http\Controllers;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Modules\Booking\Models\Booking;
use Modules\Page\Models\Setting;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phiếu xác nhận đặt tour (PDF): tour, ngày đi, số khách, đơn giá, tổng tiền,
 * tiền cọc, đã thanh toán, còn lại, hạn đóng phần còn lại, thông tin chuyển
 * khoản. Nhân viên tải về gửi khách qua Zalo / email.
 */
class PhieuXacNhanController extends Controller
{
    public function __invoke(Booking $booking): Response
    {
        abort_unless(auth()->user()?->can('view', $booking), 403);

        $booking->loadMissing(['tour:id,name,duration_days', 'departure:id,start_date']);
        $cauHinh = Setting::query()
            ->whereIn('key', ['company_name', 'legal_name', 'hotline', 'email', 'address', 'tax_code', 'license_number', 'bank_transfer', 'pdf_header_image'])
            ->pluck('value', 'key');

        $daThu = $booking->daThu();

        $pdf = Pdf::loadView('pdf.phieu-xac-nhan-tour', [
            'don' => $booking,
            'cauHinh' => $cauHinh,
            'anhDau' => self::anhDau($cauHinh['pdf_header_image'] ?? null),
            'daThu' => $daThu,
            'conLai' => max(0, (int) $booking->total_price - $daThu),
            'ngayVe' => $booking->departure?->start_date && $booking->tour?->duration_days
                ? $booking->departure->start_date->copy()->addDays($booking->tour->duration_days - 1)
                : null,
        ])->setPaper('a4')->setOption('isFontSubsettingEnabled', true); // chỉ nhúng chữ đã dùng → file nhẹ

        activity('booking')->performedOn($booking)->causedBy(auth()->user())->log('Xuất phiếu xác nhận PDF');

        return $pdf->download('Phieu-xac-nhan-'.$booking->booking_code.'.pdf');
    }

    /**
     * Băng rôn đầu phiếu dạng data URI (dompdf không tải ảnh qua mạng). Ảnh
     * nhân viên tải lên ở Cấu hình chung được ưu tiên; không có thì dùng ảnh
     * mặc định đi kèm mã nguồn. WebP / GIF đổi sang PNG vì dompdf chỉ chắc
     * chắn đọc được JPEG, PNG.
     */
    public static function anhDau(?string $duongCauHinh): ?string
    {
        $noiDung = null;
        if (filled($duongCauHinh) && Storage::disk('public')->exists($duongCauHinh)) {
            $noiDung = Storage::disk('public')->get($duongCauHinh);
        }
        $noiDung ??= is_file($macDinh = resource_path('images/phieu-xac-nhan-header.jpg')) ? file_get_contents($macDinh) : null;
        if (! $noiDung) {
            return null;
        }

        $loai = (new \finfo(FILEINFO_MIME_TYPE))->buffer($noiDung);
        if (! in_array($loai, ['image/jpeg', 'image/png'], true)) {
            $anh = @imagecreatefromstring($noiDung);
            if (! $anh) {
                return null;
            }
            ob_start();
            imagepng($anh);
            $noiDung = ob_get_clean();
            imagedestroy($anh);
            $loai = 'image/png';
        }

        return 'data:'.$loai.';base64,'.base64_encode($noiDung);
    }
}
