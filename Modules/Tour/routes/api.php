<?php

use Illuminate\Support\Facades\Route;
use Modules\Tour\Http\Controllers\TourController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('tours', TourController::class)->names('tour');
});
