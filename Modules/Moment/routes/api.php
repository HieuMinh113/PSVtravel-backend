<?php

use Illuminate\Support\Facades\Route;
use Modules\Moment\Http\Controllers\Api\MomentApiController;

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::get('moments', [MomentApiController::class, 'index']);
});