<?php

use Illuminate\Support\Facades\Route;
use Modules\Banner\Http\Controllers\Api\BannerApiController;

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::get('banners', [BannerApiController::class, 'index']);
});