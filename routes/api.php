<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MyBookingController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')->group(function () {
    // Công khai — mỗi nhóm có giới hạn tần suất riêng
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:otp');
    Route::post('resend-otp', [AuthController::class, 'resendOtp'])->middleware('throttle:otp');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login-api');

    // Bắt buộc đăng nhập
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::put('profile', [ProfileController::class, 'update']);
        Route::put('password', [ProfileController::class, 'changePassword']);
        Route::get('bookings', [MyBookingController::class, 'index']);
    });
});