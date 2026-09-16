<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AboutImageController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DestinationController;
use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Api\JobPostingController;
use App\Http\Controllers\Api\PartnerController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\SubscriberController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\EventReviewController;
use App\Http\Controllers\Api\TeamMemberController;
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

// Đội ngũ / ban lãnh đạo — công khai để trang Về chúng tôi dựng
Route::get('v1/team-members', [TeamMemberController::class, 'index'])
    ->middleware('throttle:api');

// Ảnh trang "Về chúng tôi" — admin quản lý, hiển thị công khai
Route::get('v1/about-images', [AboutImageController::class, 'index'])
    ->middleware('throttle:api');

// Nội dung marketing công khai — admin quản lý
Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::get('promotions', [PromotionController::class, 'index']);
    Route::get('destinations', [DestinationController::class, 'index']);
    Route::get('destinations-slugs', [DestinationController::class, 'slugs']);
    Route::get('destinations/{slug}', [DestinationController::class, 'show']);
    Route::get('faqs', [FaqController::class, 'index']);
    Route::get('jobs', [JobPostingController::class, 'index']);
    Route::get('jobs-slugs', [JobPostingController::class, 'slugs']);
    Route::get('jobs/{slug}', [JobPostingController::class, 'show']);
    Route::get('partners', [PartnerController::class, 'index']);
});

// Khách đăng ký nhận ưu đãi — chống spam bằng giới hạn tần suất của form liên hệ
Route::post('v1/subscribe', [SubscriberController::class, 'store'])
    ->middleware('throttle:contact');
