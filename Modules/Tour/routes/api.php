<?php

use Illuminate\Support\Facades\Route;
use Modules\Tour\Http\Controllers\Api\TourApiController;
use Modules\Tour\Http\Controllers\Api\TourQrController;

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::get('tours', [TourApiController::class, 'index']);
    Route::get('tours-slugs', [TourApiController::class, 'slugs']);
    Route::get('tours/{slug}', [TourApiController::class, 'show']);

    // Mã QR in trên tờ rơi: ghi lượt quét + trả về trang cần chuyển tới
    Route::post('qr/{id}', [TourQrController::class, 'quet'])->whereNumber('id');
});