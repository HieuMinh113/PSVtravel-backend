<?php

use Illuminate\Support\Facades\Route;
use Modules\Booking\Http\Controllers\PhieuXacNhanController;

// Phiếu xác nhận đặt tour (PDF) — nhân viên tải trong trang quản trị để gửi
// khách. Chỉ người đăng nhập trang quản trị có quyền xem đơn mới tải được.
Route::get('quan-tri/don-tour/{booking}/phieu-xac-nhan', PhieuXacNhanController::class)
    ->name('booking.phieu-xac-nhan');
