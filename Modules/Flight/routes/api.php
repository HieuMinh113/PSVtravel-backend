<?php

use Illuminate\Support\Facades\Route;
use Modules\Flight\Http\Controllers\Api\FlightApiController;

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::get('airlines', [FlightApiController::class, 'airlines']);
    Route::get('flight-deals', [FlightApiController::class, 'deals']);
});