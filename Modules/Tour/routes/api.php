<?php

use Illuminate\Support\Facades\Route;
use Modules\Tour\Http\Controllers\Api\TourApiController;

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::get('tours', [TourApiController::class, 'index']);
    Route::get('tours-slugs', [TourApiController::class, 'slugs']);
    Route::get('tours/{slug}', [TourApiController::class, 'show']);
});