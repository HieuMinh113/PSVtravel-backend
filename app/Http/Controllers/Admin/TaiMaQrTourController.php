<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MaQrTour;
use Illuminate\Support\Facades\Gate;
use Modules\Tour\Models\Tour;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tải mã QR của tour về máy (PNG để đăng mạng, SVG để in — phóng to không vỡ).
 *
 * Route nằm trong panel quản trị (AdminPanelProvider::authenticatedRoutes) nên
 * phải đăng nhập + qua 2FA như mọi trang admin; thêm kiểm tra quyền xem tour.
 */
class TaiMaQrTourController extends Controller
{
    public function __invoke(Tour $tour, string $dinhDang, MaQrTour $qr): Response
    {
        Gate::authorize('view', $tour);

        if ($dinhDang === 'svg') {
            return response($qr->svg($tour), 200, [
                'Content-Type' => 'image/svg+xml',
                'Content-Disposition' => 'attachment; filename="'.MaQrTour::tenTep($tour, 'svg').'"',
            ]);
        }

        return response($qr->png($tour), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.MaQrTour::tenTep($tour, 'png').'"',
        ]);
    }
}
