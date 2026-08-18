<?php

use Illuminate\Support\Facades\Route;
use Modules\Review\Http\Controllers\Api\ReviewApiController;

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::get('reviews/featured', [ReviewApiController::class, 'featured']);
});