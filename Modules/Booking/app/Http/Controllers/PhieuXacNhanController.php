<?php

namespace Modules\Booking\Http\Controllers;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
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
            ->whereIn('key', ['company_name', 'legal_name', 'hotline', 'email', 'address', 'tax_code', 'license_number', 'bank_transfer'])
            ->pluck('value', 'key');

        $daThu = $booking->daThu();

        $pdf = Pdf::loadView('pdf.phieu-xac-nhan-tour', [
            'don' => $booking,
            'cauHinh' => $cauHinh,
            'daThu' => $daThu,
            'conLai' => max(0, (int) $booking->total_price - $daThu),
            'ngayVe' => $booking->departure?->start_date && $booking->tour?->duration_days
                ? $booking->departure->start_date->copy()->addDays($booking->tour->duration_days - 1)
                : null,
        ])->setPaper('a4')->setOption('isFontSubsettingEnabled', true); // chỉ nhúng chữ đã dùng → file nhẹ

        activity('booking')->performedOn($booking)->causedBy(auth()->user())->log('Xuất phiếu xác nhận PDF');

        return $pdf->download('Phieu-xac-nhan-'.$booking->booking_code.'.pdf');
    }
}
