<?php

use Illuminate\Support\Facades\Route;
use Modules\Maldives\Http\Controllers\Api\MaldivesApiController;

Route::prefix('v1')->group(function () {
    Route::middleware('throttle:api')->group(function () {
        Route::get('maldives', [MaldivesApiController::class, 'index']);
        Route::get('maldives-sitemap', [MaldivesApiController::class, 'sitemap']);
        Route::get('maldives/{slug}', [MaldivesApiController::class, 'show']);
    });
    Route::post('maldives/bookings', [MaldivesApiController::class, 'dat'])->middleware('throttle:booking');
});
