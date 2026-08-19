<?php

use Illuminate\Support\Facades\Route;
use Modules\Review\Http\Controllers\Api\ReviewApiController;

Route::prefix('v1')->group(function () {
    // Công khai
    Route::get('reviews/featured', [ReviewApiController::class, 'featured'])
        ->middleware('throttle:api');

    // Phải đăng nhập mới viết được đánh giá
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('reviews/can-review/{slug}', [ReviewApiController::class, 'canReview'])
            ->middleware('throttle:api');
        Route::post('reviews', [ReviewApiController::class, 'store'])
            ->middleware('throttle:review');
    });
});