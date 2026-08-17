<?php

use Illuminate\Support\Facades\Route;
use Modules\Page\Http\Controllers\Api\PageApiController;
use Modules\Page\Http\Controllers\Api\SettingApiController;

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::get('settings', [SettingApiController::class, 'index']);
    Route::get('pages/{slug}', [PageApiController::class, 'show']);
});