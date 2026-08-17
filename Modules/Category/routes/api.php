<?php

use Illuminate\Support\Facades\Route;
use Modules\Category\Http\Controllers\Api\CategoryApiController;

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::get('categories', [CategoryApiController::class, 'index']);
});