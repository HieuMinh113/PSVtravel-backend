<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\EventReviewController;
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

// Form liên hệ ngoài website — chống spam bằng giới hạn tần suất riêng
Route::post('v1/contact', [ContactController::class, 'store'])
    ->middleware('throttle:contact');

// Yêu cầu tổ chức sự kiện / team building — dùng chung giới hạn với form liên hệ
Route::post('v1/team-building', [ContactController::class, 'teamBuilding'])
    ->middleware('throttle:contact');

// Gói sự kiện / team building — công khai để website dựng trang
Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::get('events', [EventController::class, 'index']);
    Route::get('events-slugs', [EventController::class, 'slugs']);
    Route::get('events/{slug}', [EventController::class, 'show']);
    Route::get('events/{slug}/reviews', [EventReviewController::class, 'index']);
});

// Khách gửi đánh giá gói sự kiện — chờ admin duyệt; giới hạn tần suất riêng
Route::post('v1/events/{slug}/reviews', [EventReviewController::class, 'store'])
    ->middleware('throttle:review');
