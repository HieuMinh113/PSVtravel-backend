<?php

use Illuminate\Support\Facades\Route;
use Modules\Booking\Http\Controllers\Api\BookingApiController;
use Modules\Booking\Http\Controllers\Api\BookingLookupController;

Route::prefix('v1')->group(function () {
    Route::post('bookings', [BookingApiController::class, 'store'])
        ->middleware('throttle:booking');

    // Tra cứu đơn cho khách không đăng nhập — cần đúng mã đơn + số điện thoại
    Route::post('bookings/lookup', BookingLookupController::class)
        ->middleware('throttle:lookup');
});
