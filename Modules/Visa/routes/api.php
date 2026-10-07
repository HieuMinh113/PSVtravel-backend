<?php

use Illuminate\Support\Facades\Route;
use Modules\Visa\Http\Controllers\Api\HoSoVisaApiController;
use Modules\Visa\Http\Controllers\Api\VisaApiController;

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::get('visa-countries', [VisaApiController::class, 'index']);
    Route::get('visa-countries/{slug}', [VisaApiController::class, 'show']);
});
// Khách tự nộp hồ sơ visa trên website (không bắt buộc đăng nhập)
Route::post('v1/visa-applications', [HoSoVisaApiController::class, 'store'])
    ->middleware('throttle:visa-nop');
Route::post('v1/visa-applications/{code}/files', [HoSoVisaApiController::class, 'storeFile'])
    ->middleware('throttle:visa-tep');

// Hồ sơ visa của khách đang đăng nhập — trang Tài khoản
Route::get('v1/auth/visa-cases', [HoSoVisaApiController::class, 'mine'])
    ->middleware(['auth:sanctum', 'throttle:api']);
