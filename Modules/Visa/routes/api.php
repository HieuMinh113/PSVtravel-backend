<?php

use Illuminate\Support\Facades\Route;
use Modules\Visa\Http\Controllers\Api\VisaApiController;

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::get('visa-countries', [VisaApiController::class, 'index']);
    Route::get('visa-countries/{slug}', [VisaApiController::class, 'show']);
});