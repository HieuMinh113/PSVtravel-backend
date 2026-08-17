<?php

use Illuminate\Support\Facades\Route;
use Modules\Booking\Http\Controllers\Api\BookingApiController;

Route::prefix('v1')->group(function () {
    Route::post('bookings', [BookingApiController::class, 'store'])
        ->middleware('throttle:booking');
});